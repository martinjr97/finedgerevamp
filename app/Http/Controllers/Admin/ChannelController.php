<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChannelRequest;
use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChannelController extends Controller
{
    public function index(): View
    {
        abort_unless(auth('admin')->user()?->can('channels.view'), 403);

        $channels = Channel::orderBy('name')->get();

        return view('admin.channels.index', compact('channels'));
    }

    public function create(): View
    {
        abort_unless(auth('admin')->user()?->can('channels.create'), 403);

        return view('admin.channels.create');
    }

    public function store(ChannelRequest $request): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('channels.create'), 403);

        try {
            Channel::create($request->validated());

            return redirect()
                ->route('admin.channels.index')
                ->with('status', 'Channel created successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.channels.create')
                ->withInput()
                ->with('error', 'Failed to create channel: '.$e->getMessage());
        }
    }

    public function show(Channel $channel): View
    {
        abort_unless(auth('admin')->user()?->can('channels.view'), 403);

        return view('admin.channels.show', compact('channel'));
    }

    public function edit(Channel $channel): View
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->can('channels.update') || $admin?->can('channels.edit'), 403);

        return view('admin.channels.edit', compact('channel'));
    }

    public function update(ChannelRequest $request, Channel $channel): RedirectResponse
    {
        $admin = auth('admin')->user();
        abort_unless($admin?->can('channels.update') || $admin?->can('channels.edit'), 403);

        try {
            $channel->update($request->validated());

            return redirect()
                ->route('admin.channels.show', $channel)
                ->with('status', 'Channel updated successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.channels.edit', $channel)
                ->withInput()
                ->with('error', 'Failed to update channel: '.$e->getMessage());
        }
    }

    public function destroy(Channel $channel): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->can('channels.delete'), 403);

        try {
            $channel->delete();

            return redirect()
                ->route('admin.channels.index')
                ->with('status', 'Channel deleted successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->route('admin.channels.index')
                ->with('error', 'Failed to delete channel: '.$e->getMessage());
        }
    }
}
