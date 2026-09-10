<?php

namespace App\Support\Fault;

class StacktraceFrame
{
    /**
     * @param  array<int, array{line: int, code: string, highlighted: bool}>  $codeLines
     * @param  array<string, string>  $vars
     */
    public function __construct(
        public readonly string $filename,
        public readonly ?int $lineNumber,
        public readonly ?string $functionName,
        public readonly bool $inApp,
        public readonly string $language,
        public readonly array $codeLines,
        public readonly array $vars,
    ) {}

    /**
     * @param  array<string, mixed>  $frame
     */
    public static function fromPayload(array $frame): self
    {
        $filename = $frame['filename'] ?? $frame['abs_path'] ?? '(unknown file)';

        return new self(
            filename: $filename,
            lineNumber: $frame['lineno'] ?? null,
            functionName: $frame['function'] ?? null,
            inApp: $frame['in_app'] ?? true,
            language: FrameLanguageResolver::resolve($filename),
            codeLines: self::buildCodeLines($frame),
            vars: self::formatVars($frame['vars'] ?? []),
        );
    }

    public function hasContext(): bool
    {
        return $this->codeLines !== [];
    }

    public function hasVars(): bool
    {
        return $this->vars !== [];
    }

    public function isExpandable(): bool
    {
        return $this->hasContext() || $this->hasVars();
    }

    /**
     * @param  array<string, mixed>  $frame
     * @return array<int, array{line: int, code: string, highlighted: bool}>
     */
    protected static function buildCodeLines(array $frame): array
    {
        $preContext = $frame['pre_context'] ?? [];
        $postContext = $frame['post_context'] ?? [];
        $contextLine = $frame['context_line'] ?? null;

        if ($preContext === [] && $postContext === [] && $contextLine === null) {
            return [];
        }

        $line = ($frame['lineno'] ?? 0) - count($preContext);
        $codeLines = [];

        foreach ($preContext as $code) {
            $codeLines[] = ['line' => $line++, 'code' => $code, 'highlighted' => false];
        }

        if ($contextLine !== null) {
            $codeLines[] = ['line' => $line++, 'code' => $contextLine, 'highlighted' => true];
        }

        foreach ($postContext as $code) {
            $codeLines[] = ['line' => $line++, 'code' => $code, 'highlighted' => false];
        }

        return $codeLines;
    }

    /**
     * @param  array<string, mixed>  $vars
     * @return array<string, string>
     */
    protected static function formatVars(array $vars): array
    {
        return array_map(
            fn ($value) => is_scalar($value) ? (string) $value : json_encode($value),
            $vars,
        );
    }
}
