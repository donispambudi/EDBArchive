<?php

namespace App\Http\Controllers;

use App\Models\FheJob;
use Illuminate\View\View;

class FheJobController extends Controller
{
    public function index(): View
    {
        $jobs = FheJob::query()
            ->with('creator')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('fhe_jobs.index', [
            'jobs' => $jobs,
        ]);
    }

    public function show(FheJob $fheJob): View
    {
        $fheJob->load('creator');

        return view('fhe_jobs.show', [
            'fheJob' => $fheJob,
        ]);
    }
}
