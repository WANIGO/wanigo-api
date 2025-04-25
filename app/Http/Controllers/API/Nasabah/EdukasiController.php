<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\Konten;
use App\Models\KontenArtikel;
use App\Models\KontenVideo;
use App\Models\Modul;
use App\Models\User;
use App\Models\UserProgress;
use App\Models\PointTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EdukasiController extends Controller
{
    /**
     * Mendapatkan daftar semua modul edukasi beserta progresnya.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getModuls(Request $request)
    {
        try {
            $user = $request->user();

            // Pengecekan role seperti controller TaskFlow 4
            if (!$user->isNasabah()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses ditolak. Anda bukan nasabah.',
                ], 403);
            }

            $user_id = $user->id;
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

            // Log aktivitas pengguna
            Log::info("User {$user_id} mengakses daftar modul edukasi", [
                'user_id' => $user_id,
                'total_moduls' => $totalModuls,
                'completed_moduls' => $completedModuls
            ]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'moduls' => $response,
                    'total_moduls' => $totalModuls,
                    'completed_moduls' => $completedModuls,
                    'overall_progress' => round($overallProgress, 2),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error pada getModuls: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data modul.',
            ], 500);
        }
    }

    /**
     * Mendapatkan detail modul beserta daftar kontennya.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getModulDetail(Request $request, $id)
    {
        try {
            $user = $request->user();

            // Pengecekan role seperti controller TaskFlow 4
            if (!$user->isNasabah()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses ditolak. Anda bukan nasabah.',
                ], 403);
            }

            $user_id = $user->id;
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

            // Log aktivitas pengguna
            Log::info("User {$user_id} mengakses detail modul ID {$id}", [
                'user_id' => $user_id,
                'modul_id' => $id,
                'progress' => round($progress, 2)
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $response
            ]);
        } catch (\Exception $e) {
            Log::error('Error pada getModulDetail: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil detail modul.',
            ], 500);
        }
    }

    /**
     * Mendapatkan detail konten video.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVideoDetail(Request $request, $id)
    {
        try {
            $user = $request->user();

            // Pengecekan role seperti controller TaskFlow 4
            if (!$user->isNasabah()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akses ditolak. Anda bukan nasabah.',
                ], 403);
            }

            $user_id = $user->id;
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

            // Log aktivitas pengguna
            Log::info("User {$user_id} mengakses konten video ID {$id}", [
                'user_id' => $user_id,
                'konten_id' => $id,
                'current_progress' => $userProgress->progress
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $response
            ]);
        } catch (\Exception $e) {
            Log::error('Error pada getVideoDetail: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil detail video.',
            ], 500);
        }
    }

    /**
     * Mendapatkan detail konten artikel.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getArtikelDetail($id)
    {
        try {
            $user_id = Auth::id();
            $user = User::findOrFail($user_id);

            // Periksa apakah pengguna aktif
            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun Anda tidak aktif.'
                ], 403);
            }

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

            // Log aktivitas pengguna
            Log::info("User {$user_id} mengakses konten artikel ID {$id}", [
                'user_id' => $user_id,
                'konten_id' => $id,
                'current_progress' => $userProgress->progress
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $response
            ]);
        } catch (\Exception $e) {
            Log::error('Error pada getArtikelDetail: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil detail artikel.',
            ], 500);
        }
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
        try {
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
            $user = User::findOrFail($user_id);

            // Periksa apakah pengguna aktif
            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun Anda tidak aktif.'
                ], 403);
            }

            $konten = Konten::findOrFail($id);

            if ($konten->tipe_konten !== 'video') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Konten ini bukan video'
                ], 400);
            }

            // Dapatkan progress user saat ini
            $userProgress = $konten->getProgressForUser($user_id);
            $previouslyCompleted = $userProgress ? $userProgress->status : false;

            // Update progress
            $konten->updateProgress(
                $user_id,
                $request->progress,
                $request->is_completed
            );

            // Jika konten baru saja diselesaikan (sebelumnya belum selesai), tambahkan poin ke user
            if ($request->is_completed && !$previouslyCompleted) {
                $pointsEarned = $this->addPointsToUser($user, $konten);
            } else {
                $pointsEarned = 0;
            }

            // Log aktivitas pengguna
            Log::info("User {$user_id} mengupdate progress video ID {$id}", [
                'user_id' => $user_id,
                'konten_id' => $id,
                'progress' => $request->progress,
                'is_completed' => $request->is_completed,
                'points_earned' => $pointsEarned
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Progress video berhasil diperbarui',
                'data' => [
                    'progress' => $request->progress,
                    'is_completed' => $request->is_completed,
                    'modul_progress' => round($konten->modul->calculateProgress($user_id), 2),
                    'modul_completed' => $konten->modul->isCompleted($user_id),
                    'points_earned' => $pointsEarned,
                    'total_points' => $user->points,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error pada updateVideoProgress: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui progress video.',
            ], 500);
        }
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
        try {
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
            $user = User::findOrFail($user_id);

            // Periksa apakah pengguna aktif
            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun Anda tidak aktif.'
                ], 403);
            }

            $konten = Konten::findOrFail($id);

            if ($konten->tipe_konten !== 'artikel') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Konten ini bukan artikel'
                ], 400);
            }

            // Dapatkan progress user saat ini
            $userProgress = $konten->getProgressForUser($user_id);
            $previouslyCompleted = $userProgress ? $userProgress->status : false;

            // Update progress
            $konten->updateProgress(
                $user_id,
                $request->progress,
                $request->is_completed
            );

            // Jika konten baru saja diselesaikan (sebelumnya belum selesai), tambahkan poin ke user
            if ($request->is_completed && !$previouslyCompleted) {
                $pointsEarned = $this->addPointsToUser($user, $konten);
            } else {
                $pointsEarned = 0;
            }

            // Log aktivitas pengguna
            Log::info("User {$user_id} mengupdate progress artikel ID {$id}", [
                'user_id' => $user_id,
                'konten_id' => $id,
                'progress' => $request->progress,
                'is_completed' => $request->is_completed,
                'points_earned' => $pointsEarned
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Progress artikel berhasil diperbarui',
                'data' => [
                    'progress' => $request->progress,
                    'is_completed' => $request->is_completed,
                    'modul_progress' => round($konten->modul->calculateProgress($user_id), 2),
                    'modul_completed' => $konten->modul->isCompleted($user_id),
                    'points_earned' => $pointsEarned,
                    'total_points' => $user->points,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error pada updateArtikelProgress: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui progress artikel.',
            ], 500);
        }
    }

    /**
     * Mendapatkan total poin edukatif pengguna.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserPoints()
    {
        try {
            $user_id = Auth::id();
            $user = User::findOrFail($user_id);

            // Periksa apakah pengguna aktif
            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun Anda tidak aktif.'
                ], 403);
            }

            // Dapatkan transaksi poin
            $pointTransactions = PointTransaction::where('user_id', $user_id)
                ->where('transaction_type', 'education')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            $response = [
                'total_points' => $user->points,
                'recent_transactions' => $pointTransactions->map(function ($transaction) {
                    return [
                        'id' => $transaction->id,
                        'description' => $transaction->description,
                        'points' => $transaction->points,
                        'date' => $transaction->created_at->format('Y-m-d H:i:s')
                    ];
                })
            ];

            return response()->json([
                'status' => 'success',
                'data' => $response
            ]);
        } catch (\Exception $e) {
            Log::error('Error pada getUserPoints: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data poin pengguna.',
            ], 500);
        }
    }

    /**
     * Cek status token dan otentikasi pengguna.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAuthStatus()
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token tidak valid atau sudah kedaluwarsa',
                    'is_authenticated' => false
                ], 401);
            }

            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun pengguna tidak aktif',
                    'is_authenticated' => true,
                    'is_active' => false
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User terautentikasi dan aktif',
                'data' => [
                    'is_authenticated' => true,
                    'is_active' => true,
                    'user_id' => $user->id,
                    'role' => $user->role
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error pada checkAuthStatus: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat memeriksa status autentikasi.',
                'is_authenticated' => false
            ], 500);
        }
    }

    /**
     * Menambahkan poin ke pengguna dan mencatat transaksi poin.
     *
     * @param User $user
     * @param Konten $konten
     * @return int Jumlah poin yang ditambahkan
     */
    private function addPointsToUser(User $user, Konten $konten)
    {
        try {
            // Cek apakah konten ini memiliki poin
            if ($konten->poin <= 0) {
                return 0;
            }

            // Tambahkan poin ke user
            $user->increment('points', $konten->poin);

            // Catat transaksi poin
            $transaction = new PointTransaction([
                'user_id' => $user->id,
                'points' => $konten->poin,
                'transaction_type' => 'education',
                'description' => "Menyelesaikan {$konten->tipe_konten}: {$konten->judul_konten}",
                'reference_id' => $konten->id,
                'reference_type' => 'konten'
            ]);
            $transaction->save();

            // Jika modul sudah selesai 100%, tambahkan bonus poin modul
            $modul = $konten->modul;
            if ($modul && $modul->isCompleted($user->id) && $modul->poin > 0) {
                // Cek apakah sudah pernah mendapat bonus poin modul ini
                $existingModulBonus = PointTransaction::where('user_id', $user->id)
                    ->where('transaction_type', 'education_module_completion')
                    ->where('reference_id', $modul->id)
                    ->where('reference_type', 'modul')
                    ->exists();

                if (!$existingModulBonus) {
                    // Tambahkan bonus poin modul
                    $user->increment('points', $modul->poin);

                    // Catat transaksi bonus poin modul
                    $modulTransaction = new PointTransaction([
                        'user_id' => $user->id,
                        'points' => $modul->poin,
                        'transaction_type' => 'education_module_completion',
                        'description' => "Bonus penyelesaian modul: {$modul->judul_modul}",
                        'reference_id' => $modul->id,
                        'reference_type' => 'modul'
                    ]);
                    $modulTransaction->save();

                    // Return total poin (konten + bonus modul)
                    return $konten->poin + $modul->poin;
                }
            }

            return $konten->poin;
        } catch (\Exception $e) {
            Log::error('Error saat menambahkan poin: ' . $e->getMessage());
            return 0;
        }
    }
}