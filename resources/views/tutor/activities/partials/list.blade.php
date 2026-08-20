<section class="mt-10">
    <div class="mb-4 flex items-center justify-between gap-4">
        <h2 class="text-lg font-semibold">Activities</h2>
        <a href="{{ route('tutor.activities.create', [$course, $module, $learningUnit]) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Add activity</a>
    </div>
    <p class="mb-4 text-sm text-slate-600">Activities are generic learning objects. Type engines, grading, and code execution are not enabled here.</p>

    @if ($learningUnit->activities->isEmpty())
        <p class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">No activities yet.</p>
    @else
        <ol class="space-y-3">
            @foreach ($learningUnit->activities as $index => $activity)
                <li class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-medium">{{ $activity->title }}</h3>
                            <p class="text-sm text-slate-500">{{ strtoupper($activity->type->value) }} · {{ strtoupper($activity->status->value) }}</p>
                            @if (isset($activity->configuration['instructions']))
                                <p class="mt-1 text-sm text-slate-600">{{ $activity->configuration['instructions'] }}</p>
                            @endif
                            @if ($activity->tutorPrivateConfiguration() !== [])
                                <p class="mt-1 text-xs text-slate-500">Includes tutor-private configuration</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2 text-sm">
                            @if ($index > 0)
                                @php
                                    $upOrder = $learningUnit->activities->pluck('id')->values()->all();
                                    [$upOrder[$index - 1], $upOrder[$index]] = [$upOrder[$index], $upOrder[$index - 1]];
                                @endphp
                                <form method="POST" action="{{ route('tutor.activities.reorder', [$course, $module, $learningUnit]) }}">
                                    @csrf
                                    @foreach ($upOrder as $id)
                                        <input type="hidden" name="order[]" value="{{ $id }}">
                                    @endforeach
                                    <button type="submit" class="underline">Up</button>
                                </form>
                            @endif
                            <a href="{{ route('tutor.activities.edit', [$course, $module, $learningUnit, $activity]) }}" class="underline">Edit</a>
                            <form method="POST" action="{{ route('tutor.activities.publish', [$course, $module, $learningUnit, $activity]) }}">
                                @csrf
                                <button type="submit" class="underline">Publish</button>
                            </form>
                            <form method="POST" action="{{ route('tutor.activities.unpublish', [$course, $module, $learningUnit, $activity]) }}">
                                @csrf
                                <button type="submit" class="underline">Unpublish</button>
                            </form>
                            <form method="POST" action="{{ route('tutor.activities.archive', [$course, $module, $learningUnit, $activity]) }}" onsubmit="return confirm('Archive this activity?')">
                                @csrf
                                <button type="submit" class="underline">Archive</button>
                            </form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>
