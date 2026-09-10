<?php

namespace App\Support\Fault;

class FrameLanguageResolver
{
    /**
     * @var array<string, string>
     */
    protected const LANGUAGES_BY_EXTENSION = [
        'php' => 'php',
        'js' => 'javascript',
        'mjs' => 'javascript',
        'cjs' => 'javascript',
        'jsx' => 'jsx',
        'ts' => 'typescript',
        'tsx' => 'tsx',
        'py' => 'python',
        'rb' => 'ruby',
        'java' => 'java',
        'go' => 'go',
        'rs' => 'rust',
        'c' => 'c',
        'h' => 'c',
        'cpp' => 'cpp',
        'cc' => 'cpp',
        'hpp' => 'cpp',
        'cs' => 'csharp',
        'json' => 'json',
        'html' => 'markup',
        'htm' => 'markup',
        'xml' => 'markup',
        'css' => 'css',
        'sh' => 'bash',
        'bash' => 'bash',
        'sql' => 'sql',
        'yml' => 'yaml',
        'yaml' => 'yaml',
    ];

    /**
     * Resolve the Prism.js language slug for a stacktrace frame's file, so
     * syntax highlighting isn't hardcoded to PHP for non-PHP SDK events.
     */
    public static function resolve(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return self::LANGUAGES_BY_EXTENSION[$extension] ?? 'none';
    }
}
