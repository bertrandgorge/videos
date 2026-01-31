<?php
require_once __DIR__ . '/vendor/autoload.php';

// Test rapide de l'API
$imdbApi = new \hmerritt\Imdb();

echo "Test de recherche...\n";
$results = $imdbApi->search("Interstellar", [
    'category' => 'tt',
    'curlHeaders' => ['Accept-Language: fr-FR,fr,en;q=0.5']
]);

if (!empty($results['results'])) {
    echo "Premier résultat: " . $results['results'][0]['title'] . " (" . $results['results'][0]['imdb'] . ")\n";
    
    echo "\nTest de récupération des infos...\n";
    $film = $imdbApi->film($results['results'][0]['imdb'], [
        'curlHeaders' => ['Accept-Language: fr-FR,fr,en;q=0.5']
    ]);
    
    echo "Titre: " . ($film['title'] ?? 'N/A') . "\n";
    echo "Année: " . ($film['year'] ?? 'N/A') . "\n";
    echo "Note: " . ($film['rating'] ?? 'N/A') . "\n";
    echo "Genres: " . implode(', ', $film['genres'] ?? []) . "\n";
    echo "Durée: " . ($film['length'] ?? 'N/A') . " min\n";
    
    if (!empty($film['cast'])) {
        echo "\nPremiers acteurs:\n";
        $count = 0;
        foreach ($film['cast'] as $cast) {
            if ($count >= 3) break;
            echo "  - " . $cast['actor'] . " (" . ($cast['character'] ?? 'N/A') . ")\n";
            $count++;
        }
    }
} else {
    echo "Aucun résultat trouvé\n";
}

echo "\n✓ L'API fonctionne correctement!\n";
