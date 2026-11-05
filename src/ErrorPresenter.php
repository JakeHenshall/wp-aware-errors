<?php

declare(strict_types=1);

namespace Hensh\WpAwareErrors;

use Throwable;

/**
 * Single presentation entry point for every handled exception and fatal error.
 * It generates the correlation reference, stores the sanitised internal record,
 * applies AccessPolicy and selects a protocol-appropriate response.
 */
final class ErrorPresenter
{
    public static function render(Throwable $exception): void
    {
        if (! Runtime::beginHandling()) {
            return;
        }

        try {
            $data = ExceptionData::fromThrowable($exception);
            $detailed = AccessPolicy::canViewDetails();
            $context = null;

            // Generic unauthorised errors deliberately collect no request, hook or
            // environment context. The internal record keeps only the sanitised error
            // summary needed to correlate the opaque reference.
            if ($detailed) {
                try {
                    $context = ContextCollector::collect($data);
                } catch (Throwable) {
                }
            }

            $reference = ErrorReference::generate();
            ErrorHistory::store($data, $context ?? [], $reference);

            try {
                $kind = RequestKind::detect();
            } catch (Throwable) {
                $kind = RequestKind::HTML;
            }

            if ($kind === RequestKind::HTML) {
                if ($detailed) {
                    try {
                        if (IgnitionPage::available()) {
                            IgnitionPage::render($exception, $context, false, '', '', $reference);
                            Runtime::markRendered();
                            return;
                        }
                    } catch (Throwable) {
                    }

                    try {
                        Renderer::render($data, $context ?? [], $reference);
                        Runtime::markRendered();
                        return;
                    } catch (Throwable) {
                    }
                }

                GenericErrorPage::render($reference);
                Runtime::markRendered();
                return;
            }

            try {
                $response = ErrorResponse::build($kind, $detailed, $data, $context, $reference);
            } catch (Throwable) {
                $response = ErrorResponse::generic($kind, $reference);
            }

            Runtime::markRendered();
            ErrorResponse::send($response, $kind);
        } finally {
            Runtime::endHandling();
        }
    }
}
