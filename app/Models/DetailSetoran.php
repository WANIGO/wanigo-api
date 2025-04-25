<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailSetoran extends Model
{
    use HasFactory;

    protected $table = 'detail_setoran';

    protected $fillable = [
        'setoran_sampah_id',
        'item_sampah_id',
        'berat',
        'saldo',
    ];

    protected $casts = [
        'berat' => 'decimal:2',
        'saldo' => 'decimal:2',
    ];

    /**
     * Get the setoran sampah that owns the detail.
     */
    public function setoranSampah()
    {
        return $this->belongsTo(SetoranSampah::class);
    }

    /**
     * Get the item sampah that owns the detail.
     */
    public function itemSampah()
    {
        return $this->belongsTo(KatalogSampah::class, 'item_sampah_id');
    }

    /**
     * Calculate saldo based on berat and harga per kg.
     *
     * @param float $berat
     * @param float|null $hargaPerKg
     * @return float
     */
    public function calculateSaldo($berat, $hargaPerKg = null)
    {
        if ($hargaPerKg === null) {
            $hargaPerKg = $this->itemSampah->harga_per_kg;
        }

        return $berat * $hargaPerKg;
    }

    /**
     * Set berat and automatically calculate saldo.
     *
     * @param float $berat
     * @return bool
     */
    public function setBerat($berat)
    {
        $this->berat = $berat;
        $this->saldo = $this->calculateSaldo($berat);

        $saved = $this->save();

        if ($saved) {
            // Update totals in the related setoran sampah
            $this->setoranSampah->updateTotals();
        }

        return $saved;
    }

    /**
     * Get formatted berat in kg.
     *
     * @return string
     */
    public function getBeratFormatAttribute()
    {
        return number_format($this->berat, 2, ',', '.') . ' kg';
    }

    /**
     * Get formatted saldo in Rupiah.
     *
     * @return string
     */
    public function getSaldoFormatAttribute()
    {
        return 'Rp ' . number_format($this->saldo, 0, ',', '.');
    }

    /**
     * Get harga per kg from related item sampah.
     *
     * @return float
     */
    public function getHargaPerKgAttribute()
    {
        return $this->itemSampah->harga_per_kg;
    }

    /**
     * Get formatted harga per kg in Rupiah.
     *
     * @return string
     */
    public function getHargaPerKgFormatAttribute()
    {
        return 'Rp ' . number_format($this->getHargaPerKgAttribute(), 0, ',', '.');
    }
}