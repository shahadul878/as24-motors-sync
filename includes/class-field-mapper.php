<?php
/**
 * Field Mapper - Maps AutoScout24 data to Motors fields
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Field_Mapper {
    
    /**
     * Map AutoScout24 listing to WordPress post data
     */
    public static function map_to_post_data($listing) {
        $title = $listing['details']['adProduct']['title'] ?? '';
        
        if (empty($title)) {
            $make = $listing['details']['vehicle']['classification']['make']['formatted'] ?? '';
            $model = $listing['details']['vehicle']['classification']['model']['formatted'] ?? '';
            $year = $listing['details']['vehicle']['classification']['modelYear'] ?? '';
            $title = trim($make . ' ' . $model . ' ' . $year);
        }
        
        $post_data = array(
            'post_title' => sanitize_text_field($title),
            'post_content' => wp_kses_post($listing['details']['description'] ?? ''),
            'post_type' => 'listings',
            'post_status' => 'publish',
            'post_author' => 1
        );
        
        return $post_data;
    }
    
    /**
     * Map to meta data
     */
    public static function map_to_meta_data($listing) {
        $meta = array();
        
        // Core identifiers
        $meta['autoscout24-id'] = $listing['id'];
		$meta['_as24_id']       = $listing['id']; // For backward compatibility
        $meta['as24-updated-at'] = $listing['details']['publication']['changedTimestamp'] ?? '';
        $meta['as24-created-at'] = $listing['details']['publication']['createdTimestamp'] ?? '';
        
        // VIN
        if (!empty($listing['details']['vehicle']['identifier']['vin'])) {
            $meta['vin_number'] = $listing['details']['vehicle']['identifier']['vin'];
        }
        
        // Mileage
        if (!empty($listing['details']['vehicle']['condition']['mileageInKm']['raw'])) {
            $meta['mileage'] = $listing['details']['vehicle']['condition']['mileageInKm']['raw'];
        }
        
        // Engine
        if (!empty($listing['details']['vehicle']['engine']['engineDisplacementInCCM']['raw'])) {
            $meta['engine'] = $listing['details']['vehicle']['engine']['engineDisplacementInCCM']['raw'];
            $meta['engine_power'] = $listing['details']['vehicle']['engine']['engineDisplacementInCCM']['raw'];
        }
        
        // Power
        if (!empty($listing['details']['vehicle']['engine']['power']['hp']['raw'])) {
            $meta['power-hp'] = $listing['details']['vehicle']['engine']['power']['hp']['raw'];
        }
        if (!empty($listing['details']['vehicle']['engine']['power']['kw']['raw'])) {
            $meta['power-kw'] = $listing['details']['vehicle']['engine']['power']['kw']['raw'];
        }
        
        // Price
        if (!empty($listing['details']['prices']['public']['amountInEUR']['raw'])) {
            $meta['price'] = $listing['details']['prices']['public']['amountInEUR']['raw'];
            $meta['stm_genuine_price'] = $listing['details']['prices']['public']['amountInEUR']['raw'];
        }
        
        // Registration date
        if (!empty($listing['details']['vehicle']['condition']['firstRegistrationDate']['formatted'])) {
            $meta['registration_date'] = $listing['details']['vehicle']['condition']['firstRegistrationDate']['formatted'];
        }
        
        // Fuel consumption and CO2
        if (!empty($listing['details']['vehicle']['fuels']['primary']['consumption']['combined']['raw'])) {
            $meta['fuel-consumption'] = $listing['details']['vehicle']['fuels']['primary']['consumption']['combined']['raw'];
        }
        if (!empty($listing['details']['vehicle']['fuels']['primary']['co2emissionInGramPerKm']['raw'])) {
            $meta['fuel-economy'] = $listing['details']['vehicle']['fuels']['primary']['co2emissionInGramPerKm']['raw'];
        }
        
        // Other fields
        $meta['number-of-doors'] = $listing['details']['vehicle']['numberOfDoors'] ?? '';
        $meta['number-of-seats'] = $listing['details']['vehicle']['interior']['numberOfSeats'] ?? '';
        $meta['number-of-gears'] = $listing['details']['vehicle']['engine']['numberOfGears'] ?? '';
        $meta['accident-free'] = $listing['details']['vehicle']['condition']['damage']['accidentFree'] ?? '';
        $meta['number-of-previous-owners'] = $listing['details']['vehicle']['condition']['numberOfPreviousOwners'] ?? '';
        
        // Location
        if (!empty($listing['details']['location'])) {
            self::map_location_meta($meta, $listing['details']['location']);
        }
        
        // Equipment
        if (!empty($listing['details']['vehicle']['equipment']['as24'])) {
            $meta['equipment-as24'] = $listing['details']['vehicle']['equipment']['as24'];
        }
        if (!empty($listing['details']['vehicle']['highlightedEquipment'])) {
            $meta['highlighted-equipment'] = $listing['details']['vehicle']['highlightedEquipment'];
        }
        
        // Video
        if (!empty($listing['details']['media']['youtubeLink'])) {
            $meta['gallery_video'] = $listing['details']['media']['youtubeLink'];
        }
        
        // Motors theme specific fields
        $meta['stm_car_views'] = 0;
        $meta['stm_phone_reveals'] = 0;
        $meta['car_mark_as_sold'] = '';
        $meta['is_sell_online_status'] = '';
        $meta['_price'] = $listing['details']['prices']['public']['netAmountInEUR']['raw'] ?? '';
        $meta['price'] = $listing['details']['prices']['public']['amountInEUR']['raw'] ?? '';
        $meta['stm_genuine_price'] = $listing['details']['prices']['public']['amountInEUR']['raw'] ?? '';
        $meta['title'] = $listing['details']['adProduct']['title'] ?? '';
        $meta['subtitle'] = $listing['details']['adProduct']['subTitle'] ?? '';
        $meta['car_price_form_label'] = '';
        $meta['car_price_form'] = '';
        $meta['stm_car_user'] = 1; // Admin user
        $meta['stm_car_user_date'] = current_time('mysql');
        $meta['stm_car_user_times'] = array(
            'day_one' => '09',
            'day_two' => '17',
            'day_one_hour' => '00',
            'day_two_hour' => '00'
        );
        
        return apply_filters('as24_motors_sync_meta_data', $meta, $listing);
    }
    
    /**
     * Map location to meta
     */
    private static function map_location_meta(&$meta, $location) {
        $required_keys = array('street', 'zip', 'city', 'countryCode');
        $has_all = true;
        
        foreach ($required_keys as $key) {
            if (empty($location[$key])) {
                $has_all = false;
                break;
            }
        }
        
        if ($has_all) {
            $address = $location['street'] . ', ' . $location['zip'] . ' ' . $location['city'] . ', ' . $location['countryCode'];
            $meta['stm_car_location'] = $address;
            $meta['city'] = $location['city'];
            $meta['zip'] = $location['zip'];
            $meta['country-code'] = $location['countryCode'];
        }
    }
    
    /**
     * Add taxonomies to listing
     */
    public static function add_taxonomies($post_id, $listing) {
        // Make
        if (!empty($listing['details']['vehicle']['classification']['make']['formatted'])) {
            self::add_taxonomy_term($post_id, 'make', $listing['details']['vehicle']['classification']['make']['formatted']);
        }
        
        // Model (serie)
        if (!empty($listing['details']['vehicle']['classification']['model']['formatted'])) {
            self::add_taxonomy_term($post_id, 'serie', $listing['details']['vehicle']['classification']['model']['formatted']);
        }
        
        // Year
        if (!empty($listing['details']['vehicle']['classification']['modelYear'])) {
            self::add_taxonomy_term($post_id, 'ca-year', $listing['details']['vehicle']['classification']['modelYear']);
        }
        
        // Body type
        if (!empty($listing['details']['vehicle']['bodyType']['formatted'])) {
            self::add_taxonomy_term($post_id, 'body', $listing['details']['vehicle']['bodyType']['formatted']);
        }
        
        // Fuel type
        if (!empty($listing['details']['vehicle']['fuels']['fuelCategory']['formatted'])) {
            self::add_taxonomy_term($post_id, 'fuel', $listing['details']['vehicle']['fuels']['fuelCategory']['formatted']);
        }
        
        // Transmission
        if (!empty($listing['details']['vehicle']['engine']['transmissionType']['formatted'])) {
            self::add_taxonomy_term($post_id, 'transmission', $listing['details']['vehicle']['engine']['transmissionType']['formatted']);
        }
        
        // Condition
        if (!empty($listing['details']['vehicle']['legalCategories'][0]['formatted'])) {
            self::add_taxonomy_term($post_id, 'condition', $listing['details']['vehicle']['legalCategories'][0]['formatted']);
        }
        
        // Colors
        if (!empty($listing['details']['vehicle']['bodyColor']['formatted'])) {
            self::add_taxonomy_term($post_id, 'exterior-color', $listing['details']['vehicle']['bodyColor']['formatted']);
        }
        if (!empty($listing['details']['vehicle']['interior']['upholsteryColor']['formatted'])) {
            self::add_taxonomy_term($post_id, 'interior-color', $listing['details']['vehicle']['interior']['upholsteryColor']['formatted']);
        }
        
        // Price taxonomy
        if (!empty($listing['details']['prices']['public']['amountInEUR']['raw'])) {
            self::add_taxonomy_term($post_id, 'price', $listing['details']['prices']['public']['amountInEUR']['raw']);
        }
        
        // Additional features from equipment
        self::add_equipment_features($post_id, $listing);
        
        // Motors theme specific taxonomies
        self::add_taxonomy_term($post_id, 'drive', $listing['details']['vehicle']['engine']['driveTrain']['formatted'] ?? '');
        self::add_taxonomy_term($post_id, 'mileage', $listing['details']['vehicle']['condition']['mileageInKm']['raw'] ?? '');
        self::add_taxonomy_term($post_id, 'engine', $listing['details']['vehicle']['engine']['engineDisplacementInCCM']['raw'] ?? '');
        self::add_taxonomy_term($post_id, 'fuel-consumption', $listing['details']['vehicle']['fuels']['primary']['consumption']['combined']['raw'] ?? '');
        self::add_taxonomy_term($post_id, 'power', $listing['details']['vehicle']['engine']['power']['hp']['raw'] ?? '');
        self::add_taxonomy_term($post_id, 'transmission', $listing['details']['vehicle']['engine']['transmissionType']['formatted'] ?? '');
        self::add_taxonomy_term($post_id, 'doors', $listing['details']['vehicle']['numberOfDoors'] ?? '');
        self::add_taxonomy_term($post_id, 'seats', $listing['details']['vehicle']['interior']['numberOfSeats'] ?? '');
    }
    
    /**
     * Add taxonomy term and meta
     */
    private static function add_taxonomy_term($post_id, $taxonomy, $term_name) {
        global $wpdb;
        
        $display_name = ucwords(strtolower($term_name));
        $slug = strtolower(str_replace(' ', '-', $term_name));
        
        // Check if term exists
        $term = $wpdb->get_row($wpdb->prepare(
            "SELECT t.term_id FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE t.name = %s AND tt.taxonomy = %s LIMIT 1",
            $display_name, $taxonomy
        ));
        
        if ($term) {
            $term_id = $term->term_id;
        } else {
            // Create term
            $wpdb->insert($wpdb->terms, array('name' => $display_name, 'slug' => $slug), array('%s', '%s'));
            $term_id = $wpdb->insert_id;
            
            $wpdb->insert($wpdb->term_taxonomy, array(
                'term_id' => $term_id,
                'taxonomy' => $taxonomy,
                'description' => '',
                'parent' => 0,
                'count' => 0
            ), array('%d', '%s', '%s', '%d', '%d'));
        }
        
        if ($term_id) {
            wp_set_post_terms($post_id, array($term_id), $taxonomy, true);
            update_post_meta($post_id, $taxonomy, strtolower($term_name));
        }
        
        return $term_id;
    }
    
    /**
     * Add equipment as additional features
     */
    private static function add_equipment_features($post_id, $listing) {
        $features = array();
        
        // From equipment.as24
        if (!empty($listing['details']['vehicle']['equipment']['as24'])) {
            foreach ($listing['details']['vehicle']['equipment']['as24'] as $equipment) {
                if (!empty($equipment['id']['formatted'])) {
                    $features[] = sanitize_text_field($equipment['id']['formatted']);
                }
            }
        }
        
        // From highlightedEquipment
        if (!empty($listing['details']['vehicle']['highlightedEquipment'])) {
            foreach ($listing['details']['vehicle']['highlightedEquipment'] as $highlighted) {
                if (!empty($highlighted['id']['formatted'])) {
                    $features[] = sanitize_text_field($highlighted['id']['formatted']);
                }
            }
        }
        
        if (!empty($features)) {
            $features = array_unique($features);
            update_post_meta($post_id, 'additional_features', implode(',', $features));
            
            // Add to stm_additional_features taxonomy
            foreach ($features as $feature) {
                wp_set_object_terms($post_id, $feature, 'stm_additional_features', true);
            }
        }
    }
}

