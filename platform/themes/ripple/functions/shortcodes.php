<?php

use Botble\Theme\Supports\ThemeSupport;

app()->booted(function () {

    ThemeSupport::registerGoogleMapsShortcode();
    ThemeSupport::registerYoutubeShortcode();

    if (is_plugin_active('blog')) {
        add_shortcode('featured-posts', __('Featured posts'), __('Featured posts'), function () {
            return Theme::partial('shortcodes.featured-posts');
        });

        add_shortcode('recent-posts', __('Recent posts'), __('Recent posts'), function ($shortCode) {
            return Theme::partial('shortcodes.recent-posts', ['title' => $shortCode->title]);
        });

        shortcode()->setAdminConfig('recent-posts', function ($attributes, $content) {
            return Theme::partial('shortcodes.recent-posts-admin-config', compact('attributes', 'content'));
        });

        add_shortcode('featured-categories-posts', __('Featured categories posts'), __('Featured categories posts'),
            function ($shortCode) {
                return Theme::partial('shortcodes.featured-categories-posts', ['title' => $shortCode->title]);
            });

        shortcode()->setAdminConfig('featured-categories-posts', function ($attributes, $content) {
            return Theme::partial('shortcodes.featured-categories-posts-admin-config', compact('attributes', 'content'));
        });
    }

    if (is_plugin_active('gallery')) {
        add_shortcode('all-galleries', __('All Galleries'), __('All Galleries'), function ($shortCode) {
            return Theme::partial('shortcodes.all-galleries', ['limit' => $shortCode->limit]);
        });

        shortcode()->setAdminConfig('all-galleries', function ($attributes, $content) {
            return Theme::partial('shortcodes.all-galleries-admin-config', compact('attributes', 'content'));
        });
    }
});
