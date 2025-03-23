<?php
/**
 * BoardGameGeek API integration
 */
class BGM_BGG_API {
    /**
     * Import a game from BoardGameGeek API
     * 
     * @param int $bgg_id BoardGameGeek game ID
     * @return array Result with success/failure and details
     */
    public static function import_game($bgg_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'bgm_games';
        
        // Get current user ID
        $user_id = get_current_user_id();
        
        // Check if game already exists
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_name WHERE bgg_id = %d", $bgg_id));
        
        // Simple API call with basic error handling
        $url = 'https://boardgamegeek.com/xmlapi2/thing?stats=1&id=' . $bgg_id;
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => "Failed to connect to BGG API: " . $response->get_error_message()
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Process XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        
        if ($xml === false) {
            return [
                'success' => false,
                'message' => "Failed to parse BGG API response."
            ];
        }
        
        // Extract basic game data
        try {
            $id = (string)$xml->item->attributes()->id;
            $name = (string)$xml->item->name[0]->attributes()->value;
            $thumbnail = (string)$xml->item->thumbnail;
            $minplayers = (string)$xml->item->minplayers['value'];
            $maxplayers = (string)$xml->item->maxplayers['value'];
            $minplaytime = (string)$xml->item->minplaytime['value'];
            $maxplaytime = (string)$xml->item->maxplaytime['value'];
            $complexity = (string)$xml->item->statistics->ratings->averageweight['value'];
            $rating = (string)$xml->item->statistics->ratings->average['value'];
            $bgglink = 'https://boardgamegeek.com/boardgame/' . $id;
            $description = (string)$xml->item->description;
            $year_published = isset($xml->item->yearpublished['value']) ? (string)$xml->item->yearpublished['value'] : null;
            
            // Extract categories and mechanics
            $catNames = "";
            $mechNames = "";
            $publisher = "";
            $designer = "";
            
            // Limit number of publishers/designers to prevent very long strings
            $publisherCount = 0;
            $designerCount = 0;
            $maxPublishers = 3;
            $maxDesigners = 3;
            
            foreach ($xml->item->link as $link) {
                if ((string)$link->attributes()->type == "boardgamecategory") {
                    $catNames .= (string)$link->attributes()->value . ", ";
                } elseif ((string)$link->attributes()->type == "boardgamemechanic") {
                    $mechNames .= (string)$link->attributes()->value . ", ";
                } elseif ((string)$link->attributes()->type == "boardgamepublisher" && $publisherCount < $maxPublishers) {
                    $publisher .= (string)$link->attributes()->value . ", ";
                    $publisherCount++;
                } elseif ((string)$link->attributes()->type == "boardgamedesigner" && $designerCount < $maxDesigners) {
                    $designer .= (string)$link->attributes()->value . ", ";
                    $designerCount++;
                }
            }

            // Remove the trailing ", " from the fields
            $catNames = rtrim($catNames, ", ");
            $mechNames = rtrim($mechNames, ", ");
            $publisher = rtrim($publisher, ", ");
            $designer = rtrim($designer, ", ");
            
            // Add indication if lists were truncated
            if ($publisherCount >= $maxPublishers) {
                $publisher .= " (and more)";
            }
            if ($designerCount >= $maxDesigners) {
                $designer .= " (and more)";
            }
            
            // Insert or update the game in database
            if ($existing) {
                // Update existing game
                $result = $wpdb->update(
                    $table_name,
                    [
                        'name' => $name,
                        'thumb' => $thumbnail,
                        'minplayers' => $minplayers,
                        'maxplayers' => $maxplayers,
                        'minplaytime' => $minplaytime,
                        'maxplaytime' => $maxplaytime,
                        'complexity' => $complexity,
                        'gamecats' => $catNames,
                        'gamemechs' => $mechNames,
                        'bgglink' => $bgglink,
                        'rating' => $rating,
                        'bgg_id' => $id,
                        'description' => $description,
                        'year_published' => $year_published,
                        'publisher' => $publisher,
                        'designer' => $designer,
                        'last_updated' => current_time('mysql'),
                        'added_by' => $user_id
                    ],
                    ['id' => $existing]
                );
                
                if ($result === false) {
                    return [
                        'success' => false,
                        'message' => "Database error: " . $wpdb->last_error
                    ];
                }
                
                $action = "updated";
                
            } else {
                // Insert new game
                $result = $wpdb->insert(
                    $table_name,
                    [
                        'name' => $name,
                        'thumb' => $thumbnail,
                        'minplayers' => $minplayers,
                        'maxplayers' => $maxplayers,
                        'minplaytime' => $minplaytime,
                        'maxplaytime' => $maxplaytime,
                        'complexity' => $complexity,
                        'gamecats' => $catNames,
                        'gamemechs' => $mechNames,
                        'bgglink' => $bgglink,
                        'rating' => $rating,
                        'qty' => 1,
                        'qtyrented' => 0,
                        'bgg_id' => $id,
                        'description' => $description,
                        'year_published' => $year_published,
                        'publisher' => $publisher,
                        'designer' => $designer,
                        'date_added' => current_time('mysql'),
                        'last_updated' => current_time('mysql'),
                        'added_by' => $user_id
                    ]
                );
                
                if ($result === false) {
                    return [
                        'success' => false,
                        'message' => "Database error: " . $wpdb->last_error
                    ];
                }
                
                $action = "added";
            }
            
            return [
                'success' => true,
                'action' => $action,
                'id' => $id,
                'name' => $name,
                'thumbnail' => $thumbnail,
                'minplayers' => $minplayers,
                'maxplayers' => $maxplayers,
                'minplaytime' => $minplaytime,
                'maxplaytime' => $maxplaytime,
                'complexity' => $complexity,
                'rating' => $rating,
                'catNames' => $catNames,
                'mechNames' => $mechNames,
                'bgglink' => $bgglink,
                'description' => substr($description, 0, 100) . (strlen($description) > 100 ? '...' : ''),
                'year_published' => $year_published,
                'publisher' => $publisher,
                'designer' => $designer
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error processing game data: " . $e->getMessage()
            ];
        }
    }
}