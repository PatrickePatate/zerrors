<?php

namespace App\Livewire;

use App\Models\FaultIssue;
use App\Support\Ai\IssueAnalyzer;
use Livewire\Component;
use RuntimeException;
use Throwable;

class IssueAnalysis extends Component
{
    public FaultIssue $issue;

    public ?string $error = null;

    public function mount(FaultIssue $issue): void
    {
        $this->issue = $issue;
    }

    public function analyze(IssueAnalyzer $analyzer): void
    {
        $this->error = null;
        $this->stream(content: '', replace: true, name: 'analysis-stream');

        try {
            $analyzer->analyze($this->issue, function (string $delta): void {
                $this->stream(content: e($delta), name: 'analysis-stream');
            });
            $this->issue->refresh();
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
        } catch (Throwable $e) {
            report($e);
            $this->error = 'The AI provider request failed: '.$e->getMessage();
        }
    }

    public function deepen(IssueAnalyzer $analyzer): void
    {
        $this->error = null;
        $this->stream(content: '', replace: true, name: 'deepen-stream');

        try {
            $analyzer->deepen($this->issue, function (string $delta): void {
                $this->stream(content: e($delta), name: 'deepen-stream');
            });
            $this->issue->refresh();
        } catch (RuntimeException $e) {
            $this->error = $e->getMessage();
        } catch (Throwable $e) {
            report($e);
            $this->error = 'The AI provider request failed: '.$e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.issue-analysis', [
            'organization' => $this->issue->project->organization,
        ]);
    }
}
