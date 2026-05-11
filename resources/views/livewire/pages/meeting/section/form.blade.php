<div class="p-6 space-y-6">
    <form wire:submit="save" class="space-y-6">
        {{-- Host Selection --}}
        <div>
            <x-nawasara-ui::form.label for="hostId">Host <span class="text-red-500">*</span></x-nawasara-ui::form.label>
            <select id="hostId" wire:model="hostId"
                class="w-full px-4 py-2 border rounded-lg dark:bg-neutral-800 dark:border-neutral-600" required>
                <option value="">Select a host</option>
                @foreach ($hosts as $host)
                    <option value="{{ $host->user_id }}">{{ $host->full_name }} ({{ $host->email }})</option>
                @endforeach
            </select>
            @error('hostId')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>

        {{-- Topic --}}
        <div>
            <x-nawasara-ui::form.label for="topic">Topic <span
                    class="text-red-500">*</span></x-nawasara-ui::form.label>
            <input type="text" id="topic" wire:model="topic"
                class="w-full px-4 py-2 border rounded-lg dark:bg-neutral-800 dark:border-neutral-600"
                placeholder="Meeting topic" required>
            @error('topic')
                <span class="text-red-500 text-sm">{{ $message }}</span>
            @enderror
        </div>

        {{-- Agenda --}}
        <div>
            <x-nawasara-ui::form.label for="agenda">Agenda</x-nawasara-ui::form.label>
            <textarea id="agenda" wire:model="agenda"
                class="w-full px-4 py-2 border rounded-lg dark:bg-neutral-800 dark:border-neutral-600 h-24"
                placeholder="Meeting agenda"></textarea>
        </div>

        {{-- Start Time & Duration --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <x-nawasara-ui::form.label for="startTime">Start Time <span
                        class="text-red-500">*</span></x-nawasara-ui::form.label>
                <input type="datetime-local" id="startTime" wire:model="startTime"
                    class="w-full px-4 py-2 border rounded-lg dark:bg-neutral-800 dark:border-neutral-600" required>
                @error('startTime')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror
            </div>
            <div>
                <x-nawasara-ui::form.label for="duration">Duration (minutes) <span
                        class="text-red-500">*</span></x-nawasara-ui::form.label>
                <input type="number" id="duration" wire:model="duration" min="15" max="1440"
                    class="w-full px-4 py-2 border rounded-lg dark:bg-neutral-800 dark:border-neutral-600" required>
                @error('duration')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror
            </div>
        </div>

        {{-- Password --}}
        <div>
            <x-nawasara-ui::form.label for="password">Meeting Password</x-nawasara-ui::form.label>
            <input type="password" id="password" wire:model="password"
                class="w-full px-4 py-2 border rounded-lg dark:bg-neutral-800 dark:border-neutral-600"
                placeholder="Leave blank for auto-generated">
        </div>

        {{-- Settings --}}
        <div class="border-t pt-4 dark:border-neutral-700">
            <h3 class="font-semibold mb-4">Settings</h3>
            <div class="space-y-3">
                <label class="flex items-center">
                    <input type="checkbox" wire:model="autoRecording"
                        class="rounded dark:bg-neutral-800 dark:border-neutral-600">
                    <span class="ml-2">Auto-record meeting to cloud</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" wire:model="waitingRoom"
                        class="rounded dark:bg-neutral-800 dark:border-neutral-600">
                    <span class="ml-2">Enable waiting room</span>
                </label>
            </div>
        </div>

        {{-- Recurrence --}}
        <div>
            <x-nawasara-ui::form.label for="recurrence">Recurrence</x-nawasara-ui::form.label>
            <select id="recurrence" wire:model="recurrenceType"
                class="w-full px-4 py-2 border rounded-lg dark:bg-neutral-800 dark:border-neutral-600">
                <option value="none">No recurrence</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
            </select>
        </div>

        {{-- Submit --}}
        <div class="flex gap-2 pt-4 border-t dark:border-neutral-700">
            <x-nawasara-ui::button type="submit" color="success">
                <x-slot:icon><x-lucide-save class="size-4" /></x-slot:icon>
                {{ $meetingId ? 'Update Meeting' : 'Create Meeting' }}
            </x-nawasara-ui::button>
            <x-nawasara-ui::button :href="route('nawasara-zoom.meeting.index')" color="neutral" variant="outline">
                <x-lucide-x class="size-4" />
                Cancel
            </x-nawasara-ui::button>
        </div>
    </form>
</div>
