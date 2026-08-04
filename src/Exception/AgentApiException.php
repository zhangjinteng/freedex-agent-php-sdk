<?php
declare(strict_types=1);

namespace Freedex\Agent\Exception;

class AgentApiException extends AgentSdkException
{
    /** @var int */
    private $httpStatus;
    /** @var int */
    private $codeValue;
    /** @var string */
    private $responseBody;

    public function __construct(string $message, int $httpStatus, int $codeValue, string $responseBody)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->codeValue = $codeValue;
        $this->responseBody = $responseBody;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getCodeValue(): int
    {
        return $this->codeValue;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
