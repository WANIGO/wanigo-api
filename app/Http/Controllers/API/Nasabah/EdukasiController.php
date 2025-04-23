<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\Konten;
use App\Models\KontenArtikel;
use App\Models\KontenVideo;
use App\Models\Modul;
use App\Models\UserProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EdukasiController extends Controller
{
    /**
     * Mendapatkan daftar semua modul edukasi beserta progresnya.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getModuls()
    {
        $user_id = Auth::id();
        $moduls = Modul::withCount('kontens')->get();

        $response = [];
        $totalModuls = $moduls->count();
        $completedModuls = 0;

        foreach ($moduls as $modul) {
            $progress = $modul->calculateProgress($user_id);
            $is_completed = $modul->isCompleted($user_id);

            if ($is_completed) {
                $completedModuls++;
            }

            $response[] = [
                'id' => $modul->id,
                'judul_modul' => $modul->judul_modul,
                'deskripsi' => $modul->deskripsi,
                'jumlah_konten' => $modul->kontens_count,
                'estimasi_waktu' => $modul->estimasi_waktu,
                'poin' => $modul->poin,
                'progress' => round($progress, 2),
                'is_completed' => $is_completed,
            ];
        }

        // Hitung progres keseluruhan modul
        $overallProgress = $totalModuls > 0 ? ($completedModuls / $totalModuls) * 100 : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'moduls' => $response,
                'total_moduls' => $totalModuls,
                'completed_moduls' => $completedModuls,
                'overall_progress' => round($overallProgress, 2),
            ]
        ]);
    }

    /**
     * Mendapatkan detail modul beserta daftar kontennya.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getModulDetail($id)
    {
        $user_id = Auth::id();
        $modul = Modul::with(['kontens' => function ($query) {
            $query->orderBy('urutan', 'asc');
        }])->findOrFail($id);

        $kontenList = [];
        foreach ($modul->kontens as $konten) {
            $isCompleted = $konten->isCompletedByUser($user_id);
            $progress = $konten->getProgressPercentage($user_id);

            $kontenList[] = [
                'id' => $konten->id,
                'judul_konten' => $konten->judul_konten,
                'deskripsi' => $konten->deskripsi,
                'tipe_konten' => $konten->tipe_konten,
                'durasi' => $konten->durasi, // dalam detik
                'poin' => $konten->poin,
                'is_completed' => $isCompleted,
                'progress' => $progress,
            ];
        }

        $progress = $modul->calculateProgress($user_id);

        $response = [
            'id' => $modul->id,
            'judul_modul' => $modul->judul_modul,
            'deskripsi' => $modul->deskripsi,
            'objektif_modul' => $modul->objektif_modul,
            'benefit_modul' => $modul->benefit_modul,
            'jumlah_konten' => count($kontenList),
            'estimasi_waktu' => $modul->estimasi_waktu, // total durasi dalam detik
            'poin' => $modul->poin,
            'progress' => round($progress, 2),
            'is_completed' => $modul->isCompleted($user_id),
            'kontens' => $kontenList,
        ];

        return response()->json([
            'status' => 'success',
            'data' => $response
        ]);
    }

    /**
     * Mendapatkan detail konten video.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVideoDetail($id)
    {
        $user_id = Auth::id();
        $konten = Konten::with('kontenVideo', 'modul')->findOrFail($id);

        if ($konten->tipe_konten !== 'video') {
            return response()->json([
                'status' => 'error',
                'message' => 'Konten ini bukan video'
            ], 400);
        }

        // Dapatkan progress user untuk konten ini
        $userProgress = $konten->getProgressForUser($user_id);
        if (!$userProgress) {
            // Jika belum ada progress, buat progress baru
            $userProgress = new UserProgress([
                'user_id' => $user_id,
                'konten_id' => $konten->id,
                'status' => false,
                'progress' => 0,
            ]);
            $userProgress->save();
        }

        // Dapatkan konten lainnya dari modul yang sama
        $otherKontens = Konten::where('modul_id', $konten->modul_id)
            ->where('id', '!=', $konten->id)
            ->orderBy('urutan', 'asc')
            ->get()
            ->map(function ($item) use ($user_id) {
                return [
                    'id' => $item->id,
                    'judul_konten' => $item->judul_konten,
                    'tipe_konten' => $item->tipe_konten,
                    'durasi' => $item->durasi,
                    'is_completed' => $item->isCompletedByUser($user_id),
                ];
            });

        $response = [
            'id' => $konten->id,
            'judul_konten' => $konten->judul_konten,
            'deskripsi' => $konten->deskripsi,
            'video_url' => $konten->kontenVideo->video_url,
            'durasi' => $konten->durasi,
            'poin' => $konten->poin,
            'modul_id' => $konten->modul_id,
            'judul_modul' => $konten->modul->judul_modul,
            'progress' => $userProgress->progress,
            'is_completed' => $userProgress->status,
            'other_kontens' => $otherKontens,
        ];

        return response()->json([
            'status' => 'success',
            'data' => $response
        ]);
    }

    /**
     * Mendapatkan detail konten artikel.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getArtikelDetail($id)
    {
        $user_id = Auth::id();
        $konten = Konten::with(['kontenArtikel', 'modul', 'kontenImages' => function ($query) {
            $query->ordered();
        }])->findOrFail($id);

        if ($konten->tipe_konten !== 'artikel') {
            return response()->json([
                'status' => 'error',
                'message' => 'Konten ini bukan artikel'
            ], 400);
        }

        // Dapatkan progress user untuk konten ini
        $userProgress = $konten->getProgressForUser($user_id);
        if (!$userProgress) {
            // Jika belum ada progress, buat progress baru
            $userProgress = new UserProgress([
                'user_id' => $user_id,
                'konten_id' => $konten->id,
                'status' => false,
                'progress' => 0,
            ]);
            $userProgress->save();
        }

        // Dapatkan gambar pendukung artikel
        $images = $konten->kontenImages->map(function ($image) {
            return [
                'id' => $image->id,
                'image_url' => $image->image_url,
                'caption' => $image->caption,
                'urutan' => $image->urutan,
            ];
        });

        // Dapatkan konten lainnya dari modul yang sama
        $otherKontens = Konten::where('modul_id', $konten->modul_id)
            ->where('id', '!=', $konten->id)
            ->orderBy('urutan', 'asc')
            ->get()
            ->map(function ($item) use ($user_id) {
                return [
                    'id' => $item->id,
                    'judul_konten' => $item->judul_konten,
                    'tipe_konten' => $item->tipe_konten,
                    'durasi' => $item->durasi,
                    'is_completed' => $item->isCompletedByUser($user_id),
                ];
            });

        $response = [
            'id' => $konten->id,
            'judul_konten' => $konten->judul_konten,
            'deskripsi' => $konten->deskripsi,
            'content' => $konten->kontenArtikel->content,
            'thumbnail_url' => $konten->kontenArtikel->thumbnail_url,
            'durasi' => $konten->durasi, // estimasi waktu baca dalam detik
            'poin' => $konten->poin,
            'modul_id' => $konten->modul_id,
            'judul_modul' => $konten->modul->judul_modul,
            'progress' => $userProgress->progress,
            'is_completed' => $userProgress->status,
            'images' => $images,
            'other_kontens' => $otherKontens,
        ];

        return response()->json([
            'status' => 'success',
            'data' => $response
        ]);
    }

    /**
     * Update progress konten video.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateVideoProgress(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'progress' => 'required|numeric|min:0|max:100',
            'is_completed' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 400);
        }

        $user_id = Auth::id();
        $konten = Konten::findOrFail($id);

        if ($konten->tipe_konten !== 'video') {
            return response()->json([
                'status' => 'error',
                'message' => 'Konten ini bukan video'
            ], 400);
        }

        // Update progress
        $konten->updateProgress(
            $user_id,
            $request->progress,
            $request->is_completed
        );

        // Jika konten selesai, tambahkan poin ke user
        if ($request->is_completed) {
            // Disini Anda bisa menambahkan kode untuk menambah poin user
            // Misalnya: $user->addPoints($konten->poin);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Progress video berhasil diperbarui',
            'data' => [
                'progress' => $request->progress,
                'is_completed' => $request->is_completed,
                'modul_progress' => round($konten->modul->calculateProgress($user_id), 2),
                'modul_completed' => $konten->modul->isCompleted($user_id),
            ]
        ]);
    }

    /**
     * Update progress konten artikel.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateArtikelProgress(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'progress' => 'required|numeric|min:0|max:100',
            'is_completed' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 400);
        }

        $user_id = Auth::id();
        $konten = Konten::findOrFail($id);

        if ($konten->tipe_konten !== 'artikel') {
            return response()->json([
                'status' => 'error',
                'message' => 'Konten ini bukan artikel'
            ], 400);
        }

        // Update progress
        $konten->updateProgress(
            $user_id,
            $request->progress,
            $request->is_completed
        );

        // Jika konten selesai, tambahkan poin ke user
        if ($request->is_completed) {
            // Disini Anda bisa menambahkan kode untuk menambah poin user
            // Misalnya: $user->addPoints($konten->poin);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Progress artikel berhasil diperbarui',
            'data' => [
                'progress' => $request->progress,
                'is_completed' => $request->is_completed,
                'modul_progress' => round($konten->modul->calculateProgress($user_id), 2),
                'modul_completed' => $konten->modul->isCompleted($user_id),
            ]
        ]);
    }
}