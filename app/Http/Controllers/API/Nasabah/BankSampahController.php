<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\BankSampah;
use App\Models\MemberBankSampah;
use App\Models\JamOperasionalBankSampah;
use App\Models\KatalogSampah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BankSampahController extends Controller
{
    /**
     * Mendapatkan daftar bank sampah.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = BankSampah::query();

        // Filter berdasarkan keyword (nama bank sampah)
        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $query->where('nama_bank_sampah', 'like', "%{$keyword}%");
        }

        // Filter berdasarkan status operasional
        if ($request->has('status_operasional')) {
            $query->where('status_operasional', $request->status_operasional);
        }

        // Filter berdasarkan kategori sampah
        if ($request->has('kategori_sampah')) {
            $kategoriSampah = $request->kategori_sampah;

            $query->whereHas('katalogSampah', function($q) use ($kategoriSampah) {
                $q->where('kategori_sampah', $kategoriSampah);
            });
        }

        // Filter berdasarkan jarak (jika ada latitude dan longitude)
        if ($request->has('latitude') && $request->has('longitude')) {
            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius ?? 10; // Default 10 km

            // Haversine formula untuk menghitung jarak
            $query->selectRaw("*,
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$latitude, $longitude, $latitude])
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
        }

        $bankSampah = $query->get();

        // Tambahkan status keanggotaan untuk setiap bank sampah
        $userId = Auth::id();
        foreach ($bankSampah as $bank) {
            $member = MemberBankSampah::where('user_id', $userId)
                ->where('bank_sampah_id', $bank->id)
                ->first();

            $bank->member_status = $member ? $member->status_keanggotaan : 'bukan_nasabah';
        }

        return response()->json([
            'success' => true,
            'data' => $bankSampah,
            'count' => $bankSampah->count()
        ]);
    }

    /**
     * Mendapatkan daftar bank sampah yang terhubung dengan nasabah.
     *
     * @return \Illuminate\Http\Response
     */
    public function getBankSampahList()
    {
        $userId = Auth::id();

        $bankSampah = BankSampah::whereIn('id', function($query) use ($userId) {
            $query->select('bank_sampah_id')
                  ->from('member_bank_sampah')
                  ->where('user_id', $userId)
                  ->where('status_keanggotaan', 'aktif');
        })->get();

        return response()->json([
            'success' => true,
            'data' => $bankSampah
        ]);
    }

    /**
     * Mendapatkan detail bank sampah.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $bankSampah = BankSampah::with(['jamOperasional', 'katalogSampah'])->find($id);

        if (!$bankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Bank sampah tidak ditemukan'
            ], 404);
        }

        // Cek status keanggotaan nasabah di bank sampah ini
        $userId = Auth::id();
        $memberStatus = 'bukan_nasabah';
        $memberData = null;

        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $id)
            ->first();

        if ($memberBankSampah) {
            $memberStatus = $memberBankSampah->status_keanggotaan;
            $memberData = $memberBankSampah;
        }

        // Cek apakah bank sampah sedang buka
        $hariIni = Carbon::now()->dayOfWeek;
        $jamOperasional = JamOperasionalBankSampah::where('bank_sampah_id', $id)
            ->where('day_of_week', $hariIni)
            ->first();

        $sedangBuka = false;
        if ($jamOperasional) {
            $sedangBuka = $jamOperasional->isBuka();
        }

        // Informasi kategori sampah yang diterima
        $kategoriSampah = $bankSampah->katalogSampah
            ->pluck('kategori_sampah')
            ->unique()
            ->values();

        $jenisKategori = 'tidak_ada';
        if ($kategoriSampah->contains(0) && $kategoriSampah->contains(1)) {
            $jenisKategori = 'kering_dan_basah';
        } elseif ($kategoriSampah->contains(0)) {
            $jenisKategori = 'kering';
        } elseif ($kategoriSampah->contains(1)) {
            $jenisKategori = 'basah';
        }

        return response()->json([
            'success' => true,
            'data' => $bankSampah,
            'member_status' => $memberStatus,
            'member_data' => $memberData,
            'sedang_buka' => $sedangBuka,
            'kategori_sampah' => $jenisKategori
        ]);
    }

    /**
     * Mencari bank sampah terdekat.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function findNearby(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'sometimes|numeric|min:1|max:50',
            'kategori_sampah' => 'sometimes|in:0,1' // 0=kering, 1=basah
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radius = $request->radius ?? 10; // Default 10 km

        $query = BankSampah::selectRaw("*,
            (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
            [$latitude, $longitude, $latitude])
            ->where('status_operasional', true)
            ->having('distance', '<=', $radius);

        // Filter berdasarkan kategori sampah
        if ($request->has('kategori_sampah')) {
            $kategoriSampah = $request->kategori_sampah;

            $query->whereHas('katalogSampah', function($q) use ($kategoriSampah) {
                $q->where('kategori_sampah', $kategoriSampah);
            });
        }

        $bankSampah = $query->orderBy('distance')->get();

        // Tambahkan status keanggotaan untuk setiap bank sampah
        $userId = Auth::id();
        foreach ($bankSampah as $bank) {
            $member = MemberBankSampah::where('user_id', $userId)
                ->where('bank_sampah_id', $bank->id)
                ->first();

            $bank->member_status = $member ? $member->status_keanggotaan : 'bukan_nasabah';
        }

        return response()->json([
            'success' => true,
            'data' => $bankSampah,
            'count' => $bankSampah->count()
        ]);
    }

    /**
     * Mendapatkan jam operasional bank sampah.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getJamOperasional($id)
    {
        $bankSampah = BankSampah::find($id);

        if (!$bankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Bank sampah tidak ditemukan'
            ], 404);
        }

        $jamOperasional = JamOperasionalBankSampah::where('bank_sampah_id', $id)
            ->orderBy('day_of_week')
            ->get();

        // Format jam operasional untuk tampilan
        $formatted = [];
        foreach ($jamOperasional as $jam) {
            $formatted[] = [
                'day_of_week' => $jam->day_of_week,
                'day_name' => $jam->getDayNameAttribute(),
                'open_time' => Carbon::parse($jam->open_time)->format('H:i'),
                'close_time' => Carbon::parse($jam->close_time)->format('H:i'),
                'jam_operasional_format' => $jam->getJamOperasionalFormatAttribute()
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Mendapatkan katalog sampah bank sampah.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getKatalogSampah($id, Request $request)
    {
        $bankSampah = BankSampah::find($id);

        if (!$bankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Bank sampah tidak ditemukan'
            ], 404);
        }

        $query = KatalogSampah::where('bank_sampah_id', $id)
                  ->where('status', 'aktif');

        // Filter berdasarkan kategori sampah
        if ($request->has('kategori_sampah')) {
            $query->where('kategori_sampah', $request->kategori_sampah);
        }

        $katalogSampah = $query->orderBy('nama_sampah')->get();

        return response()->json([
            'success' => true,
            'data' => $katalogSampah
        ]);
    }

    /**
     * Filter bank sampah berdasarkan peta.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function mapFilter(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius' => 'sometimes|numeric|min:1|max:50',
            'kategori_sampah' => 'sometimes|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $radius = $request->radius ?? 10; // Default 10 km

        $query = BankSampah::selectRaw("*,
            (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
            [$latitude, $longitude, $latitude])
            ->having('distance', '<=', $radius);

        // Filter berdasarkan kategori sampah
        if ($request->has('kategori_sampah')) {
            $kategoriSampah = $request->kategori_sampah;

            $query->whereHas('katalogSampah', function($q) use ($kategoriSampah) {
                $q->where('kategori_sampah', $kategoriSampah);
            });
        }

        $bankSampah = $query->orderBy('distance')->get();

        // Tambahkan status keanggotaan dan status operasional realtime
        $userId = Auth::id();
        $hariIni = Carbon::now()->dayOfWeek;

        foreach ($bankSampah as $bank) {
            // Status keanggotaan
            $member = MemberBankSampah::where('user_id', $userId)
                ->where('bank_sampah_id', $bank->id)
                ->first();

            $bank->member_status = $member ? $member->status_keanggotaan : 'bukan_nasabah';

            // Status operasional realtime
            $jamOperasional = JamOperasionalBankSampah::where('bank_sampah_id', $bank->id)
                ->where('day_of_week', $hariIni)
                ->first();

            $bank->sedang_buka = $jamOperasional ? $jamOperasional->isBuka() : false;
        }

        return response()->json([
            'success' => true,
            'data' => $bankSampah,
            'count' => $bankSampah->count()
        ]);
    }
}