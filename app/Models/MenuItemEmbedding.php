<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemEmbedding extends Model
{
    protected $table = 'menu_item_embeddings';

    protected $fillable = [
        'menu_item_id',
        'embedding',
        'model',
        'text_hash',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    /**
     * Get the menu item this embedding belongs to.
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * Check if embedding needs refresh based on text hash.
     */
    public function needsRefresh(string $newTextHash): bool
    {
        return $this->text_hash !== $newTextHash;
    }

    /**
     * Calculate cosine similarity between two embeddings.
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Find similar menu items by embedding.
     */
    public static function findSimilar(array $targetEmbedding, int $tenantId, int $limit = 5, ?int $excludeItemId = null): array
    {
        $embeddings = self::query()
            ->with('menuItem')
            ->whereHas('menuItem', function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->where('is_active', true);
            })
            ->when($excludeItemId, fn($q) => $q->where('menu_item_id', '!=', $excludeItemId))
            ->get();

        $similarities = [];

        foreach ($embeddings as $embedding) {
            $similarity = self::cosineSimilarity($targetEmbedding, $embedding->embedding);
            $similarities[] = [
                'menu_item' => $embedding->menuItem,
                'similarity' => $similarity,
            ];
        }

        // Sort by similarity descending
        usort($similarities, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($similarities, 0, $limit);
    }
}
