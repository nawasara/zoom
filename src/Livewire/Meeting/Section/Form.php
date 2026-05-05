<?php

namespace Nawasara\Zoom\Livewire\Meeting\Section;

use Livewire\Component;
use Nawasara\Zoom\Models\ZoomMeeting;
use Nawasara\Zoom\Repositories\ZoomUserRepository;
use Nawasara\Zoom\Jobs\Meetings\CreateZoomMeetingJob;
use Nawasara\Zoom\Jobs\Meetings\UpdateZoomMeetingJob;

class Form extends Component
{
    public ?int $meetingId = null;

    public string $hostId = '';
    public string $topic = '';
    public ?string $startTime = null;
    public int $duration = 60;
    public string $password = '';
    public string $agenda = '';
    public bool $autoRecording = false;
    public bool $waitingRoom = false;
    public string $recurrenceType = 'none';

    public function mount(?ZoomMeeting $meeting = null)
    {
        if ($meeting) {
            $this->meetingId = $meeting->id;
            $this->hostId = $meeting->host_id;
            $this->topic = $meeting->topic;
            $this->startTime = $meeting->start_time?->format('Y-m-d\TH:i');
            $this->duration = $meeting->duration;
            $this->password = $meeting->password ?? '';
            $this->agenda = $meeting->agenda ?? '';
            $this->autoRecording = $meeting->auto_recording !== 'none';
            $this->waitingRoom = $meeting->waiting_room ?? false;
        }
    }

    public function save()
    {
        $this->validate([
            'hostId' => 'required|string',
            'topic' => 'required|string|max:255',
            'startTime' => 'required|date_format:Y-m-d\TH:i',
            'duration' => 'required|integer|min:15|max:1440',
        ]);

        $data = [
            'topic' => $this->topic,
            'start_time' => $this->startTime,
            'duration' => $this->duration,
            'password' => $this->password,
            'agenda' => $this->agenda,
            'settings' => [
                'auto_recording' => $this->autoRecording ? 'cloud' : 'none',
                'waiting_room_settings' => [
                    'is_waiting_room_enabled' => $this->waitingRoom,
                ],
            ],
        ];

        if ($this->meetingId) {
            $meeting = ZoomMeeting::find($this->meetingId);
            UpdateZoomMeetingJob::dispatch($meeting->meeting_id, $data);
            $this->dispatch('meeting-updated');
        } else {
            $meeting = ZoomMeeting::create([
                'host_id' => $this->hostId,
                'topic' => $this->topic,
                'start_time' => $this->startTime,
                'duration' => $this->duration,
                'sync_status' => 'pending',
            ]);

            CreateZoomMeetingJob::dispatch($meeting->meeting_id, $this->hostId, $data);
            $this->dispatch('meeting-created');
        }
    }

    public function render()
    {
        $userRepo = new ZoomUserRepository();

        return view('nawasara-zoom::livewire.pages.meeting.section.form', [
            'hosts' => $userRepo->active()->get(),
        ]);
    }
}
