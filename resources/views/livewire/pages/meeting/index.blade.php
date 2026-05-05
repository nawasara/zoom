<div>
    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Zoom Meetings</x-nawasara-ui::page.title>

        <x-slot name="description">
            Schedule, manage, and track all your Zoom meetings
        </x-slot>

        <x-slot name="actions">
            <x-nawasara-ui::page.actions>
                <x-nawasara-ui::button :href="route('nawasara-zoom.meeting.create')" color="success" permission="zoom.meeting.create">
                    <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                    Buat Meeting
                </x-nawasara-ui::button>
            </x-nawasara-ui::page.actions>
        </x-slot>

        <livewire:nawasara-zoom.meeting.section.table />
    </x-nawasara-ui::page.container>
</div>
