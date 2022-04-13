<?php

use core\Application;
use core\Debug;
use core\Handler;
use core\Response;
use DynamicDB\Entity\File;

final class ResponseReady extends Handler
{
    /**
     * @var ViewFactory
     */
    private $viewFactory;

    public function __construct($data = null)
    {
        $container = Application::getContainer();
        $this->viewFactory = $container->get('view_factory');

        parent::__construct($data);
    }

    protected function handle($data)
    {
        $response = $data->response;

        if ($response instanceof Response) {
            Debug::dump($response->getContent());

            if ($response->getResponseCode() === Response::STATUS_NOT_FOUND) {
                $response->setContent($this->viewFactory->createView('error/404'));
            }

            if ($response->getResponseCode() === Response::STATUS_FORBIDDEN) {
                $response->setContent($this->viewFactory->createView('error/403'));
            }
        }

        if ($response instanceof File) {
            $this->outputFile($response);
        }
    }

    private function outputFile(File $file): void
    {
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $file->getType());
        header('Content-Disposition: attachment; filename=' . $file->getName());
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . $file->getSize());

        ob_clean();
        flush();
        readfile($file->getPath());

        Application::stop();
    }
}
