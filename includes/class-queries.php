<?php
/**
 * Optimized GraphQL Queries
 * 
 * @package AS24_Motors_Sync
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class AS24_Queries {
    
    /**
     * Get lightweight query for fetching only listing IDs and timestamps
     */
    public static function get_ids_only_query($page = 1, $size = 50) {
        return "query GetListingIds {
            search {
                listings(metadata: { page: $page, size: $size }) {
                    listings {
                        id
                        details {
                            publication {
                                changedTimestamp
                                createdTimestamp
                            }
                        }
                    }
                    metadata {
                        currentPage
                        totalItems
                        totalPages
                        pageSize
                    }
                }
            }
        }";
    }
    
    /**
     * Get optimized query for essential listing data (60 fields vs 800+ lines)
     */
    public static function get_essential_listings_query($page = 1, $size = 2) {
        return '{
            search {
                listings(metadata: { page: ' . $page . ', size: ' . $size . ' }) {
                    listings {
                        id
                        details {
                            description
                            vehicle {
                                classification {
                                    modelYear
                                    make { raw formatted }
                                    model { raw formatted }
                                }
                                engine {
                                    engineDisplacementInCCM { raw formatted }
                                    transmissionType { raw formatted }
                                    driveTrain { raw formatted }
                                    power {
                                        hp { raw formatted }
                                    }
                                }
                                fuels {
                                    primary {
                                        type { raw formatted }
                                        consumption {
                                            combined { raw formatted }
                                        }
                                    }
                                    fuelCategory { raw formatted }
                                }
                                condition {
                                    firstRegistrationDate { raw formatted }
                                    mileageInKm { raw formatted }
                                }
                                bodyColor { raw formatted }
                                bodyType { raw formatted }
                                legalCategories { raw formatted }
                            }
                            prices {
                                public {
                                    amountInEUR { raw formatted }
                                }
                            }
                            media {
                                youtubeLink
                                images {
                                    ... on StandardImage {
                                        formats {
                                            webp {
                                                size540x405
                                                size640x480
                                                size800x600
                                                size1280x960
                                                size2560x1920
                                            }
                                            jpg {
                                                size540x405
                                                size640x480
                                                size800x600
                                                size1280x960
                                                size2560x1920
                                            }
                                        }
                                    }
                                }
                            }
                            location {
			                    countryCode
			                    zip
			                    city
			                    street
			                }
                            adProduct {
                                title
                            }
                            publication {
                                changedTimestamp
                                createdTimestamp
                            }
                        }
                    }
                    metadata {
                        currentPage
                        totalItems
                        totalPages
                        pageSize
                    }
                }
            }
        }';
    }
    
    /**
     * Get query for fetching a single listing by GUID
     * Uses more efficient single listing endpoint
     * 
     * @param string $listing_guid AutoScout24 listing GUID
     * @return string GraphQL query
     */
    public static function get_single_listing_query($listing_guid) {
        return "query GetSingleListing {
            search {
                listing(guid: \"$listing_guid\") {
                    searchResultType
                    searchResultSection
                    id
                    details {
                        description
                        webPage
                        identifier {
                            id
                            legacyId
                            offerReference
                            crossReferenceId
                        }
                        publication {
                            changedTimestamp
                            createdTimestamp
                            changedTimestampWithOffset
                            createdTimestampWithOffset
                            isNew
                            isRenewed
                            state
                            accurateState
                        }
                        vehicle {
                            classification {
                                modelVersionInput
                                modelYear
                                make { raw formatted }
                                model { raw formatted }
                            }
                            engine {
                                numberOfGears
                                numberOfCylinders
                                engineDisplacementInCCM { raw formatted }
                                transmissionType { raw formatted }
                                driveTrain { raw formatted }
                                power {
                                    kw { raw formatted }
                                    hp { raw formatted }
                                }
                            }
                            fuels {
                                primary {
                                    source
                                    type { raw formatted }
                                    consumption {
                                        combined { raw formatted }
                                        urban { raw formatted }
                                        extraUrban { raw formatted }
                                        combinedWithFallback { raw formatted isFallback }
                                        urbanWithFallback { raw formatted isFallback }
                                        extraUrbanWithFallback { raw formatted isFallback }
                                    }
                                    co2emissionInGramPerKm { raw formatted }
                                    co2emissionInGramPerKmWithFallback { raw formatted isFallback }
                                }
                                additional {
                                    source
                                    type { raw formatted }
                                    consumption {
                                        combined { raw formatted }
                                        extraUrbanWithFallback { raw formatted isFallback }
                                        urbanWithFallback { raw formatted isFallback }
                                        combinedWithFallback { raw formatted isFallback }
                                        extraUrban { raw formatted }
                                        urban { raw formatted }
                                    }
                                    co2emissionInGramPerKm { raw formatted }
                                    co2emissionInGramPerKmWithFallback { raw formatted isFallback }
                                }
                                allFuelTypes { raw formatted }
                                fuelCategory { raw formatted }
                                battery {
                                    chargingTime {
                                        from10to80percent { raw formatted }
                                    }
                                    ownershipType { raw formatted }
                                }
                            }
                            bodyColorOriginal
                            hasCarRegistration { raw formatted }
                            condition {
                                carpassMileageUrl
                                numberOfPreviousOwners
                                nonSmoking
                                newInspection
                                firstRegistrationDate { raw formatted }
                                mileageInKm { raw formatted }
                            }
                            interior {
                                upholstery { raw formatted }
                                upholsteryColor { raw formatted }
                            }
                            equipment {
                                userInput
                                as24 {
                                    equipmentCategory { raw formatted }
                                    category { raw formatted }
                                    id { raw formatted }
                                }
                                dat {
                                    oem {
                                        id
                                        text
                                        type
                                        as24TaxonomyId
                                    }
                                }
                            }
                            bodyColor { raw formatted }
                            bodyType { raw formatted }
                            legalCategories { raw formatted }
                        }
                        prices {
                            public {
                                vatRate
                                negotiable
                                taxDeductible
                                taxDeductibleNote
                                listingId
                                listingChangedDate
                                amountInEUR { raw formatted }
                                netAmountInEUR { raw formatted }
                            }
                        }
                        availability {
                            inDays
                            fromDate { raw formatted }
                        }
                        adProduct {
                            title
                            subTitle
                            listItemSubTitle
                            makeModelTitle
                            versionTitle
                            has360Image
                            isListingBoost
                            tier
                            appliedTier
                        }
                        media {
                            basicUrl
                            youtubeLink
                            images {
                                ... on StandardImage {
                                    formats {
                                        webp {
                                            size540x405
                                            size640x480
                                            size800x600
                                            size1280x960
                                            size2560x1920
                                        }
                                    }
                                }
                            }
                        }
                        location {
		                    countryCode
		                    zip
		                    city
		                    street
		                }
                    }
                }
            }
        }";
    }
    
    /**
     * Get total count query
     */
    public static function get_total_count_query() {
        return "query TotalListings {
            search {
                listings(metadata: { size: 1 }) {
                    metadata {
                        totalItems
                        totalPages
                    }
                }
            }
        }";
    }
    
    /**
     * Make API request
     */
    public static function make_request($query, $variables = array()) {
        AS24_Logger::debug('Making API request...', 'api');
        $credentials = as24_motors_sync()->get_api_credentials();
        
        if (!$credentials) {
            AS24_Logger::error('API credentials not configured', 'api');
            return new WP_Error('no_credentials', 'API credentials not configured');
        }
        
        AS24_Logger::debug('Using username: ' . $credentials['username'], 'api');
        $auth = base64_encode($credentials['username'] . ':' . $credentials['password']);
        
        $request_body = json_encode(array(
            'query' => $query,
            'variables' => $variables
        ));
        AS24_Logger::debug('Request body: ' . $request_body, 'api');
        
        $response = wp_remote_post('https://listing-search.api.autoscout24.com/graphql', array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $auth,
                'User-Agent' => 'AS24-Motors-Sync/' . AS24_MOTORS_SYNC_VERSION
            ),
            'body' => $request_body,
            'timeout' => 60,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            AS24_Logger::error('API request failed: ' . $response->get_error_message(), 'api');
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        AS24_Logger::debug('Response status: ' . $status_code, 'api');
        AS24_Logger::debug('Response headers: ' . print_r($headers, true), 'api');
        
        $body = wp_remote_retrieve_body($response);
        AS24_Logger::debug('Response body: ' . $body, 'api');
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            AS24_Logger::error('Failed to parse JSON response: ' . json_last_error_msg(), 'api');
            return new WP_Error('json_error', 'Failed to parse API response: ' . json_last_error_msg());
        }
        
        if (isset($data['errors'])) {
            AS24_Logger::error('API returned errors: ' . json_encode($data['errors']), 'api');
            return new WP_Error('api_error', $data['errors'][0]['message']);
        }
        
        AS24_Logger::debug('API request successful', 'api');
        return $data;
    }
}



