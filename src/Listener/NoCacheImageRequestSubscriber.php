<?php

declare(strict_types=1);

namespace AM\InterventionRequest\Listener;

use AM\InterventionRequest\Event\RequestEvent;
use AM\InterventionRequest\NextGenFile;
use AM\InterventionRequest\Processor\ChainProcessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

final class NoCacheImageRequestSubscriber implements EventSubscriberInterface
{
    private ChainProcessor $processor;

    /**
     * @param ChainProcessor $processor
     */
    public function __construct(ChainProcessor $processor)
    {
        $this->processor = $processor;
    }

    /**
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => ['onRequest', 0]
        ];
    }

    /**
     * @param RequestEvent $requestEvent
     * @return void
     */
    public function onRequest(RequestEvent $requestEvent): void
    {
        if (false === $requestEvent->getInterventionRequest()->getConfiguration()->hasCaching()) {
            $request = $requestEvent->getRequest();
            $nativePath = $requestEvent->getInterventionRequest()->getConfiguration()->getImagesPath() .
                '/' . $request->get('image');
            $nativeImage = new NextGenFile($nativePath);
            $image = $this->processor->process($nativeImage, $request);

            if ($nativeImage->isNextGen()) {
                $response = new Response(
                    (string) $image->encode($nativeImage->getNextGenExtension(), $requestEvent->getQuality()),
                    Response::HTTP_OK,
                    [
                        'Content-Type' => $nativeImage->getNextGenMimeType(),
                        'Content-Disposition' => 'filename="' . $nativeImage->getRequestedFile()->getFilename() . '"',
                        'X-IR-Cached' => '0',
                        'X-IR-First-Gen' => '1',
                    ]
                );
            } else {
                $response = new Response(
                    (string) $image->encode(null, $requestEvent->getQuality()),
                    Response::HTTP_OK,
                    [
                        'Content-Type' => $image->mime(),
                        'Content-Disposition' => 'filename="' . $nativeImage->getFilename() . '"',
                        'X-IR-Cached' => '0',
                        'X-IR-First-Gen' => '1',
                    ]
                );
            }
            $response->setLastModified(new \DateTime('now'));
            $requestEvent->setResponse($response);
        }
    }
}
