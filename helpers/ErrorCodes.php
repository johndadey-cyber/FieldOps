<?php
declare(strict_types=1);

final class ErrorCodes
{
    public const OK               = 200;
    public const CREATED          = 201;
    public const BAD_REQUEST      = 400;
    public const CSRF_INVALID     = 400;
    public const FORBIDDEN        = 403;
    public const NOT_FOUND        = 404;
    public const VALIDATION_ERROR = 422;
    public const SERVER_ERROR     = 500;
}
