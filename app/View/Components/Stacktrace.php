<?php

namespace App\View\Components;

use App\Support\Fault\StacktraceExceptionValue;
use Illuminate\View\Component;
use Illuminate\View\View;

class Stacktrace extends Component
{
    /**
     * @var array<int, StacktraceExceptionValue>
     */
    public array $values;

    /**
     * @param  array<string, mixed>  $exception
     */
    public function __construct(array $exception)
    {
        $this->values = array_map(
            fn (array $value) => StacktraceExceptionValue::fromPayload($value),
            array_reverse($exception['values'] ?? []),
        );
    }

    public function render(): View
    {
        return view('components.stacktrace');
    }
}
