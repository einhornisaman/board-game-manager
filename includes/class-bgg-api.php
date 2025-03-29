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
            $name_actual = (string)$xml->item->name[0]->attributes()->value;
            $name = str_replace("'", "", $name_actual);
            $thumbnail = (string)$xml->item->thumbnail;
            $minplayers = (string)$xml->item->minplayers['value'];
            $maxplayers = (string)$xml->item->maxplayers['value'];
            $minplaytime = (string)$xml->item->minplaytime['value'];
            $maxplaytime = (string)$xml->item->maxplaytime['value'];
            $complexity_actual = (string)$xml->item->statistics->ratings->averageweight['value'];
            $complexity = round((float)$complexity_actual, 2);
            $rating_actual = (string)$xml->item->statistics->ratings->average['value'];
            $rating = round((float)$rating_actual, 2);
            $bgglink = 'https://boardgamegeek.com/boardgame/' . $id;
            $description = (string)$xml->item->description;
            $year_published = isset($xml->item->yearpublished['value']) ? (string)$xml->item->yearpublished['value'] : null;
            $primary_type = (string)$xml->item->attributes()->type;
            
            // Get subtypes for this item
            $subtypes = array();
            foreach ($xml->item->link as $link) {
                $link_type = (string)$link->attributes()->type;
                if (strpos($link_type, 'boardgame') === 0 && $link_type !== $primary_type) {
                    $subtypes[] = $link_type;
                }
            }
            $subtypes_str = !empty($subtypes) ? implode(',', array_unique($subtypes)) : null;
            
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
                        'primary_type' => $primary_type,
                        'subtypes' => $subtypes_str,
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
                        'primary_type' => $primary_type,
                        'subtypes' => $subtypes_str,
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
                'designer' => $designer,
                'primary_type' => $primary_type,
                'subtypes' => $subtypes_str
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Error processing game data: " . $e->getMessage()
            ];
        }
    }

    /**
     * Search for games on BoardGameGeek
     * 
     * @param string $search Search term
     * @param bool $include_details Whether to include detailed information like rank and thumbnail
     * @param array $thing_types Array of BGG thing types to include (default: ['boardgame'])
     * @return array|WP_Error Array of search results or WP_Error on failure
     */
    public function search_games($search, $include_details = false, $thing_types = ['boardgame']) {
        try {
            // URL encode the search term
            $search = urlencode($search);
            
            // Make initial search request with all types
            $response = wp_remote_get("https://boardgamegeek.com/xmlapi2/search?type=boardgame,boardgameexpansion,boardgameaccessory,rpgitem,videogame&query={$search}");
            
            if (is_wp_error($response)) {
                return new WP_Error('bgg_api_error', $response->get_error_message());
            }
            
            // Get response body
            $body = wp_remote_retrieve_body($response);
            
            // Parse XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                return new WP_Error('bgg_api_error', 'Failed to parse BGG API response: ' . $errors[0]->message);
            }
            
            // Extract results
            $results = array();
            $game_ids = array();
            
            foreach ($xml->item as $item) {
                $id = (string)$item->attributes()->id;
                $name = '';
                $year = '';
                
                // Get primary name
                foreach ($item->name as $nameNode) {
                    if ((string)$nameNode->attributes()->type === 'primary') {
                        $name = (string)$nameNode->attributes()->value;
                        break;
                    }
                }
                
                // If no primary name found, use first name
                if (empty($name) && isset($item->name[0])) {
                    $name = (string)$item->name[0]->attributes()->value;
                }
                
                // Get year if available
                if (isset($item->yearpublished)) {
                    $year = (string)$item->yearpublished->attributes()->value;
                }
                
                if (!empty($name)) {
                    $game_ids[] = $id;
                }
            }
            
            // If no games found, return empty array
            if (empty($game_ids)) {
                return array();
            }
            
            // Get detailed information for all games
            $batch_size = 10;
            $batches = array_chunk($game_ids, $batch_size);
            $results = array();
            
            foreach ($batches as $batch) {
                $ids_string = implode(',', $batch);
                $thing_url = "https://boardgamegeek.com/xmlapi2/thing?id={$ids_string}&stats=1";
                
                $thing_response = wp_remote_get($thing_url);
                
                if (is_wp_error($thing_response)) {
                    return new WP_Error('bgg_api_error', 'Failed to fetch game details: ' . $thing_response->get_error_message());
                }
                
                $thing_body = wp_remote_retrieve_body($thing_response);
                $thing_xml = simplexml_load_string($thing_body);
                
                if ($thing_xml === false) {
                    $errors = libxml_get_errors();
                    libxml_clear_errors();
                    return new WP_Error('bgg_api_error', 'Failed to parse game details: ' . $errors[0]->message);
                }
                
                foreach ($thing_xml->item as $item) {
                    $id = (string)$item->attributes()->id;
                    $primary_type = (string)$item->attributes()->type;
                    
                    // Get all types for this item
                    $item_types = array($primary_type);
                    
                    // Check for additional types in the links
                    foreach ($item->link as $link) {
                        $link_type = (string)$link->attributes()->type;
                        if (strpos($link_type, 'boardgame') === 0) {
                            $item_types[] = $link_type;
                        }
                    }
                    
                    // Check if this item has any of the requested types
                    $has_requested_type = false;
                    foreach ($thing_types as $requested_type) {
                        if (in_array($requested_type, $item_types)) {
                            $has_requested_type = true;
                            break;
                        }
                    }
                    
                    if (!$has_requested_type) {
                        continue;
                    }
                    
                    // Get primary name
                    $name = '';
                    foreach ($item->name as $nameNode) {
                        if ((string)$nameNode->attributes()->type === 'primary') {
                            $name = (string)$nameNode->attributes()->value;
                            break;
                        }
                    }
                    
                    // Get year
                    $year = isset($item->yearpublished['value']) ? (string)$item->yearpublished['value'] : '';
                    
                    // Get rank
                    $rank = 999999;
                    if (isset($item->statistics->ratings->ranks->rank)) {
                        foreach ($item->statistics->ratings->ranks->rank as $rank_item) {
                            if ((string)$rank_item->attributes()->type === 'subtype' && 
                                (string)$rank_item->attributes()->name === 'boardgame') {
                                if ((string)$rank_item->attributes()->value !== 'Not Ranked') {
                                    $rank = (int)$rank_item->attributes()->value;
                                }
                                break;
                            }
                        }
                    }
                    
                    // Get thumbnail
                    $thumbnail = isset($item->thumbnail) ? (string)$item->thumbnail : '';
                    
                    // Set the primary type for display (use first matching requested type)
                    $display_type = $primary_type;
                    foreach ($thing_types as $preferred_type) {
                        if (in_array($preferred_type, $item_types)) {
                            $display_type = $preferred_type;
                            break;
                        }
                    }
                    
                    $results[] = array(
                        'id' => $id,
                        'name' => $name,
                        'year' => $year,
                        'type' => $display_type,
                        'types' => $item_types,
                        'thumbnail' => $thumbnail,
                        'rank' => $rank
                    );
                }
            }
            
            // Sort by rank
            usort($results, function($a, $b) {
                return $a['rank'] - $b['rank'];
            });
            
            return $results;
        } catch (Exception $e) {
            return new WP_Error('bgg_api_error', 'Error searching games: ' . $e->getMessage());
        }
    }

    /**
     * Get game details from BoardGameGeek API
     * 
     * @param int $game_id BoardGameGeek game ID
     * @return array|false Game details array or false on failure
     */
    public function get_game($game_id) {
        // Make API request
        $url = 'https://boardgamegeek.com/xmlapi2/thing?stats=1&id=' . $game_id;
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Process XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        
        if ($xml === false || !isset($xml->item)) {
            return false;
        }
        
        $item = $xml->item;
        
        try {
            // Get primary name
            $name = '';
            foreach ($item->name as $nameNode) {
                if ((string)$nameNode->attributes()->type === 'primary') {
                    $name = (string)$nameNode->attributes()->value;
                    break;
                }
            }
            
            // If no primary name found, use first name
            if (empty($name) && isset($item->name[0])) {
                $name = (string)$item->name[0]->attributes()->value;
            }
            
            // Extract categories and mechanics
            $categories = array();
            $mechanics = array();
            
            foreach ($item->link as $link) {
                $type = (string)$link->attributes()->type;
                $value = (string)$link->attributes()->value;
                
                if ($type === 'boardgamecategory') {
                    $categories[] = $value;
                } elseif ($type === 'boardgamemechanic') {
                    $mechanics[] = $value;
                }
            }
            
            return array(
                'id' => (string)$item->attributes()->id,
                'name' => $name,
                'year' => isset($item->yearpublished['value']) ? (string)$item->yearpublished['value'] : '',
                'thumbnail' => isset($item->thumbnail) ? (string)$item->thumbnail : '',
                'description' => (string)$item->description,
                'minplayers' => (string)$item->minplayers['value'],
                'maxplayers' => (string)$item->maxplayers['value'],
                'minplaytime' => (string)$item->minplaytime['value'],
                'maxplaytime' => (string)$item->maxplaytime['value'],
                'complexity' => round((float)$item->statistics->ratings->averageweight['value'], 2),
                'rating' => round((float)$item->statistics->ratings->average['value'], 2),
                'categories' => implode(', ', $categories),
                'mechanics' => implode(', ', $mechanics)
            );
            
        } catch (Exception $e) {
            return false;
        }
    }
}