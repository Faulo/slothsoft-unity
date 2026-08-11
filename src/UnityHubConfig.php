<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use Slothsoft\Unity\Command\UnityProcessOutputHandlerInterface;

final class UnityHubConfig {

    public bool $loggingEnabled = false;

    public bool $throwOnFailure = false;

    public bool $propagateProcessExitCodes = false;

    public int $processTimeout = 0;

    public ?UnityProcessOutputHandlerInterface $processOutputHandler = null;
}
