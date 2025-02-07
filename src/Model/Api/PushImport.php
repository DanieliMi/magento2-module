<?php

declare(strict_types=1);

namespace Omikron\Factfinder\Model\Api;

use Omikron\FactFinder\Communication\Client\ClientBuilder;
use Omikron\FactFinder\Communication\Client\ClientException;
use Omikron\FactFinder\Communication\Resource\AdapterFactory;
use Omikron\FactFinder\Communication\Version;
use Omikron\Factfinder\Model\Config\CommunicationConfig;
use Omikron\Factfinder\Model\Config\ExportConfig;
use Psr\Log\LoggerInterface;

class PushImport
{
    /** @var CommunicationConfig */
    private $communicationConfig;

    /** @var CredentialsFactory */
    private $credentialsFactory;

    /** @var ExportConfig */
    private $exportConfig;

    /** @var LoggerInterface */
    private $logger;

    /** @var ClientBuilder */
    private $clientBuilder;

    /** @var string */
    private $pushImportResult;

    public function __construct(
        ClientBuilder $clientBuilder,
        CredentialsFactory $credentialsFactory,
        CommunicationConfig $communicationConfig,
        ExportConfig $exportConfig,
        LoggerInterface $logger
    ) {
        $this->clientBuilder       = $clientBuilder;
        $this->credentialsFactory  = $credentialsFactory;
        $this->communicationConfig = $communicationConfig;
        $this->exportConfig        = $exportConfig;
        $this->logger              = $logger;
    }

    public function execute(int $storeId): bool
    {
        $clientBuilder = $this->clientBuilder
            ->withServerUrl($this->communicationConfig->getAddress())
            ->withCredentials($this->credentialsFactory->create());

        $adapterFactory = new AdapterFactory(
            $clientBuilder,
            $this->communicationConfig->getVersion(),
            $this->communicationConfig->getApiVersion()
        );
        $importAdapter = $adapterFactory->getImportAdapter();
        $channel       = $this->communicationConfig->getChannel($storeId);
        $dataTypes     = $this->exportConfig->getPushImportDataTypes($storeId);

        if (!$dataTypes) {
            return false;
        }

        if ($this->communicationConfig->getVersion() === Version::NG && $importAdapter->running($channel)) {
            throw new ClientException("Can't start a new import process. Another one is still going");
        }

        $responses = [];
        foreach ($dataTypes as $dataType) {
            $responses = array_merge($responses, $importAdapter->import($channel, $dataType));
        }

        $this->pushImportResult = $this->prepareListFromPushImportResponses($responses);

        return true;
    }

    public function getPushImportResult(): string
    {
        return $this->pushImportResult;
    }

    private function prepareListFromPushImportResponses(array $responses): string
    {
        return strtolower($this->communicationConfig->getVersion()) === 'ng' ? $this->ngResponse($responses) : $this->standardResponse($responses);
    }

    private function ngResponse(array $responses): string
    {
        $list = '<ul>%s</ul>';
        $listContent = '';

        foreach ($responses as $response) {
            $importType = sprintf('<li><b>%s push import type</b></li>', $response['importType']);

            $statusMessagesList = sprintf('<ul>%s</ul>', implode('', array_map(function ($message) {
                return sprintf('<li>%s</li>', $message);
            }, $response['statusMessages'])));
            $statusMessages = sprintf('<li><i>Status messages</i></li><li>%s</li>', $statusMessagesList);

            $importType .= $statusMessages;
            $listContent .= $importType;
        }

        return sprintf($list, $listContent);
    }

    private function standardResponse(array $responses): string
    {
        $list = '<ul>%s</ul>';
        $listContent = '';

        if (!empty($responses['status'])) {
            $statusMessagesList = sprintf('<ul>%s</ul>', implode('', array_map(function ($message) {
                return sprintf('<li>%s</li>', $message);
            }, $responses['status'])));

            $statusMessages = sprintf('<li><i>Status messages</i></li><li>%s</li>', $statusMessagesList);
            $listContent .= $statusMessages;
        }

        return sprintf($list, $listContent);
    }
}
