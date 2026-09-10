<?php

namespace Nawasara\Zoom\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Daftar yang tidak memuat sebuah kolom tidak boleh menghapus kolom itu.
 *
 * Satu siklus sinkronisasi memanggil beberapa daftar berbeda untuk user yang
 * sama — `scheduled`, `upcoming`, `previous_meetings`, lalu Reports API — dan
 * Zoom mengembalikan bentuk yang berbeda untuk masing-masing. `scheduled`
 * memuat `agenda` dan `settings`; `previous_meetings` dan Reports tidak.
 *
 * Semuanya melewati syncMeeting() yang sama. Dengan `?? null`, daftar yang
 * lebih miskin menimpa data dari daftar yang lebih kaya, lalu daftar berikutnya
 * mengembalikannya — bolak-balik setiap lima menit, selamanya.
 *
 * Terbukti 10 September 2026: seluruh 35 agenda rapat kosong di basis data
 * produksi, dan 243.412 dari 244.254 baris activity log adalah pembalikan itu —
 * 99,7% jejak audit, menenggelamkan perubahan peran, kredensial, dan kamera.
 */
class MeetingSyncFieldPreservationTest extends TestCase
{
    /**
     * Salinan aturan yang dipakai syncMeeting(): kolom bergantung-sumber hanya
     * ditulis bila kuncinya benar-benar ada di respons.
     *
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>  kolom yang akan ditulis
     */
    private function kolomDitulis(array $remote): array
    {
        $nilai = [
            'topic' => $remote['topic'] ?? '(tanpa judul)',
            'status' => $remote['status'] ?? 'ended',
        ];

        foreach (['timezone', 'password', 'agenda', 'join_url'] as $kolom) {
            if (array_key_exists($kolom, $remote)) {
                $nilai[$kolom] = $remote[$kolom];
            }
        }

        if (array_key_exists('settings', $remote)) {
            $nilai['auto_recording'] = $remote['settings']['auto_recording'] ?? 'none';
            $nilai['waiting_room'] = $remote['settings']['waiting_room_settings']['is_waiting_room_enabled'] ?? false;
        }

        return $nilai;
    }

    /** Bentuk yang dikembalikan daftar `scheduled` — lengkap. */
    private function dariScheduled(): array
    {
        return [
            'id' => 8123,
            'topic' => 'Rapat Koordinasi',
            'agenda' => 'Hari : Jumat, Tempat : Ruang Rapat Lantai 2',
            'status' => 'waiting',
            'settings' => [
                'auto_recording' => 'cloud',
                'waiting_room_settings' => ['is_waiting_room_enabled' => true],
            ],
        ];
    }

    /** Bentuk yang dikembalikan `previous_meetings` — tanpa agenda & settings. */
    private function dariPreviousMeetings(): array
    {
        return [
            'id' => 8123,
            'topic' => 'Rapat Koordinasi',
            'status' => 'ended',
        ];
    }

    /**
     * Inti perkaranya: lintasan kedua tidak boleh menyentuh agenda.
     */
    public function test_daftar_tanpa_agenda_tidak_menghapus_agenda(): void
    {
        $this->assertArrayNotHasKey(
            'agenda',
            $this->kolomDitulis($this->dariPreviousMeetings()),
            'daftar yang tidak memuat agenda tetap menulis kolom itu — agenda akan terhapus',
        );
    }

    /**
     * `?? null` — cara lama — memang menghapusnya. Membuktikan bug-nya nyata.
     */
    public function test_cara_lama_menghapus_agenda(): void
    {
        $remote = $this->dariPreviousMeetings();
        $caraLama = ['agenda' => $remote['agenda'] ?? null];

        $this->assertNull($caraLama['agenda']);
        $this->assertArrayHasKey('agenda', $caraLama, 'cara lama menulis null ke agenda yang sudah terisi');
    }

    /** Daftar yang MEMANG memuat agenda tetap harus menulisnya. */
    public function test_daftar_dengan_agenda_tetap_menulisnya(): void
    {
        $ditulis = $this->kolomDitulis($this->dariScheduled());

        $this->assertSame('Hari : Jumat, Tempat : Ruang Rapat Lantai 2', $ditulis['agenda']);
    }

    /**
     * `settings` hanya ada di /meetings. Tanpa penjagaan, tiap lintasan Reports
     * mengembalikan auto_recording ke 'none' dan mematikan waiting_room.
     */
    public function test_daftar_tanpa_settings_tidak_mereset_rekaman(): void
    {
        $ditulis = $this->kolomDitulis($this->dariPreviousMeetings());

        $this->assertArrayNotHasKey('auto_recording', $ditulis);
        $this->assertArrayNotHasKey('waiting_room', $ditulis);
    }

    /**
     * `null` yang Zoom kirim SENGAJA berbeda dari kunci yang tidak disertakan.
     * Itu sebabnya array_key_exists, bukan isset atau `??`.
     */
    public function test_null_yang_dikirim_sengaja_tetap_ditulis(): void
    {
        $ditulis = $this->kolomDitulis([
            'id' => 8123,
            'topic' => 'Rapat',
            'agenda' => null,      // dikirim, dan memang kosong
        ]);

        $this->assertArrayHasKey('agenda', $ditulis);
        $this->assertNull($ditulis['agenda']);
    }

    /**
     * Simulasi satu siklus penuh: scheduled → previous_meetings, seperti
     * urutan sebenarnya di syncUserMeetings().
     */
    public function test_satu_siklus_penuh_mempertahankan_agenda(): void
    {
        $baris = [];

        foreach ([$this->dariScheduled(), $this->dariPreviousMeetings()] as $remote) {
            $baris = array_merge($baris, $this->kolomDitulis($remote));
        }

        $this->assertSame(
            'Hari : Jumat, Tempat : Ruang Rapat Lantai 2',
            $baris['agenda'],
            'agenda hilang setelah lintasan previous_meetings',
        );
        $this->assertSame('cloud', $baris['auto_recording']);
        $this->assertTrue($baris['waiting_room']);

        // Yang memang berubah tetap ikut: rapat sudah selesai.
        $this->assertSame('ended', $baris['status']);
    }
}
