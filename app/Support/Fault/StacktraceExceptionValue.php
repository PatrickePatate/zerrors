<?php

namespace App\Support\Fault;

class StacktraceExceptionValue
{
    /**
     * @param  array<int, StacktraceFrame>  $frames
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $message,
        public readonly array $frames,
        public readonly int $importantFrameIndex,
    ) {}

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromPayload(array $value): self
    {
        $frames = array_map(
            fn (array $frame) => StacktraceFrame::fromPayload($frame),
            array_reverse($value['stacktrace']['frames'] ?? []),
        );

        $importantIndex = self::findImportantFrameIndex($frames);

        return new self(
            type: $value['type'] ?? 'Error',
            message: $value['value'] ?? null,
            frames: $frames,
            importantFrameIndex: $importantIndex,
        );
    }

    /**
     * @param  array<int, StacktraceFrame>  $frames
     */
    protected static function findImportantFrameIndex(array $frames): int
    {
        foreach ($frames as $index => $frame) {
            if ($frame->inApp) {
                return $index;
            }
        }

        return 0;
    }
}
