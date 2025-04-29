<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\ArtikelGallery;
use App\Models\KontenArtikel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ArtikelGalleryController extends Controller
{
    /**
     * Menampilkan semua gambar galeri untuk artikel tertentu.
     *
     * @param int $artikelId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($artikelId)
    {
        $artikel = KontenArtikel::findOrFail($artikelId);
        $galleries = ArtikelGallery::where('konten_artikel_id', $artikelId)
            ->ordered()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $galleries
        ]);
    }

    /**
     * Menyimpan gambar galeri baru.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $artikelId
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $artikelId)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'caption' => 'nullable|string|max:255',
            'urutan' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        // Cek apakah artikel ada
        $artikel = KontenArtikel::findOrFail($artikelId);

        // Upload gambar
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $path = $image->storeAs('artikel-galleries', $imageName, 'public');
            $imageUrl = Storage::url($path);

            // Hitung urutan jika tidak disediakan
            $urutan = $request->urutan ?? ArtikelGallery::where('konten_artikel_id', $artikelId)->max('urutan') + 1;

            // Simpan data galeri
            $gallery = ArtikelGallery::create([
                'konten_artikel_id' => $artikelId,
                'image_url' => $imageUrl,
                'caption' => $request->caption,
                'urutan' => $urutan
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Gambar berhasil diunggah',
                'data' => $gallery
            ], Response::HTTP_CREATED);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Gagal mengunggah gambar'
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Menampilkan gambar galeri tertentu.
     *
     * @param int $artikelId
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($artikelId, $id)
    {
        $gallery = ArtikelGallery::where('konten_artikel_id', $artikelId)
            ->where('id', $id)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $gallery
        ]);
    }

    /**
     * Memperbarui gambar galeri tertentu.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $artikelId
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $artikelId, $id)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'caption' => 'nullable|string|max:255',
            'urutan' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        // Cek apakah galeri ada
        $gallery = ArtikelGallery::where('konten_artikel_id', $artikelId)
            ->where('id', $id)
            ->firstOrFail();

        // Update gambar jika ada
        if ($request->hasFile('image')) {
            // Hapus gambar lama jika bukan gambar default
            if ($gallery->image_url) {
                $oldPath = str_replace(Storage::url(''), '', $gallery->image_url);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Upload gambar baru
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $path = $image->storeAs('artikel-galleries', $imageName, 'public');
            $gallery->image_url = Storage::url($path);
        }

        // Update data lain
        if ($request->has('caption')) {
            $gallery->caption = $request->caption;
        }

        if ($request->has('urutan')) {
            $gallery->urutan = $request->urutan;
        }

        $gallery->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Galeri berhasil diperbarui',
            'data' => $gallery
        ]);
    }

    /**
     * Menghapus gambar galeri tertentu.
     *
     * @param int $artikelId
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($artikelId, $id)
    {
        $gallery = ArtikelGallery::where('konten_artikel_id', $artikelId)
            ->where('id', $id)
            ->firstOrFail();

        // Hapus file gambar
        if ($gallery->image_url) {
            $path = str_replace(Storage::url(''), '', $gallery->image_url);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $gallery->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Gambar berhasil dihapus'
        ]);
    }

    /**
     * Mengubah urutan gambar galeri.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $artikelId
     * @return \Illuminate\Http\JsonResponse
     */
    public function reorder(Request $request, $artikelId)
    {
        $validator = Validator::make($request->all(), [
            'galleries' => 'required|array',
            'galleries.*.id' => 'required|exists:artikel_galleries,id',
            'galleries.*.urutan' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        foreach ($request->galleries as $item) {
            ArtikelGallery::where('id', $item['id'])
                ->where('konten_artikel_id', $artikelId)
                ->update(['urutan' => $item['urutan']]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Urutan galeri berhasil diperbarui'
        ]);
    }
}