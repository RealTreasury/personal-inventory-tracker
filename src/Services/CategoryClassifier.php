<?php

namespace RealTreasury\Inventory\Services;

use RealTreasury\Inventory\Settings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CategoryClassifier {

    /**
     * Suggest a category slug for an inventory item using GPT.
     *
     * @param string $item_title Item title.
     * @param string $item_notes Item notes.
     * @return string Suggested category slug or empty string on failure.
     */
    public static function suggest_category( $item_title, $item_notes ) {
        $settings = Settings::get_settings();
        $api_key  = $settings['gpt_api_key'] ?? '';
        if ( ! $api_key ) {
            return '';
        }

        $terms = get_terms(
            array(
                'taxonomy'   => 'pit_category',
                'hide_empty' => false,
            )
        );
        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return '';
        }

        $choices = array_map(
            function ( $term ) {
                return $term->slug;
            },
            $terms
        );

        $prompt = 'Choose the best matching category slug for the given item title and notes from this list: ' .
            implode( ', ', $choices ) . '. Respond with only the slug.';

        $request_body = array(
            'model'    => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role'    => 'system',
                    'content' => $prompt,
                ),
                array(
                    'role'    => 'user',
                    'content' => 'Title: ' . $item_title . "\nNotes: " . $item_notes,
                ),
            ),
            'max_tokens' => 20,
        );

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ),
                'body'    => wp_json_encode( $request_body ),
                'timeout' => 15,
            )
        );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['choices'][0]['message']['content'] ) ) {
            return '';
        }

        $slug = sanitize_title( trim( $data['choices'][0]['message']['content'] ) );
        return $slug;
    }
}

\class_alias( __NAMESPACE__ . '\\CategoryClassifier', 'PIT\\Services\\CategoryClassifier' );
