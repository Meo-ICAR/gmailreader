<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    protected $fillable = [
        'message_id',
        'attachment_id',
        'filename',
        'safe_filename',
        'mime_type',
        'size',
        'storage_path',
        'downloaded'
    ];

    protected $casts = [
        'downloaded' => 'boolean',
        'size' => 'integer',
    ];

    /**
     * Relazione con EmailInteraction (se necessario)
     * Nota: Un allegato può essere collegato a più EmailInteraction con lo stesso message_id
     */
    public function emailInteractions()
    {
        return $this->hasMany(EmailInteraction::class, 'message_id', 'message_id');
    }

    /**
     * Ottieni il percorso completo del file
     */
    public function getFullPathAttribute()
    {
        return $this->storage_path ? storage_path('app/' . $this->storage_path) : null;
    }

    /**
     * Verifica se il file esiste fisicamente su disco
     */
    public function fileExists()
    {
        return $this->storage_path && file_exists($this->full_path);
    }

    /**
     * Ottieni la dimensione leggibile
     */
    public function getReadableSizeAttribute()
    {
        if (!$this->size) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Scope per filtrare allegati scaricati
     */
    public function scopeDownloaded($query)
    {
        return $query->where('downloaded', true);
    }

    /**
     * Scope per filtrare allegati non ancora scaricati
     */
    public function scopeNotDownloaded($query)
    {
        return $query->where('downloaded', false);
    }

    /**
     * Scope per filtrare per tipo MIME
     */
    public function scopeOfType($query, $mimeType)
    {
        return $query->where('mime_type', 'like', $mimeType . '%');
    }
}
