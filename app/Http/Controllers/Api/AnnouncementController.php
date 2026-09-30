<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Announcement::ordered()->get()->map(fn ($a) => $this->present($a))]);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json(['data' => $this->present($announcement)]);
    }

    public function store(Request $request): JsonResponse
    {
        $announcement = Announcement::create($this->validated($request, true));

        return response()->json(['data' => $this->present($announcement)], 201);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $announcement->update($this->validated($request, false));

        return response()->json(['data' => $this->present($announcement->fresh())]);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(null, 204);
    }

    protected function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'title'          => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'message'        => ['nullable', 'string', 'max:500'],
            'style'          => ['sometimes', Rule::in(array_keys(Announcement::STYLES))],
            'link_url'       => ['nullable', 'url:http,https', 'max:2048'],
            'link_label'     => ['nullable', 'string', 'max:60'],
            'is_active'      => ['sometimes', 'boolean'],
            'is_dismissible' => ['sometimes', 'boolean'],
            'starts_at'      => ['nullable', 'date'],
            'ends_at'        => ['nullable', 'date', 'after:starts_at'],
            'position'       => ['sometimes', 'integer', 'min:0', 'max:999'],
        ]);

        return $creating ? $data + ['style' => 'primary', 'is_active' => true, 'is_dismissible' => true, 'position' => 0] : $data;
    }

    protected function present(Announcement $a): array
    {
        return [
            ...$a->only(['id', 'title', 'message', 'style', 'link_url', 'link_label', 'is_active', 'is_dismissible', 'position']),
            'starts_at'  => $a->starts_at?->toIso8601String(),
            'ends_at'    => $a->ends_at?->toIso8601String(),
            'is_visible' => $a->isVisible(),
        ];
    }
}
