<?php

namespace BitApps\Pi\src\Integrations\WooCommerce\Services;

use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Integrations\IntegrationHelper;
use BitApps\Pi\src\Integrations\WooCommerce\helper\WooCommerceHelper;
use WC_Comments;

if (!defined('ABSPATH')) {
    exit;
}

final class ReviewService extends BaseService
{
    public function getAllReviews()
    {
        $approvedReviews = get_comments(
            [
                'post_type' => 'product',
                'status'    => 'approve',
                'meta_key'  => 'rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required to filter reviews by rating
            ]
        );

        $ratingSummary = [];
        $reviewsData = [];

        foreach ($approvedReviews as $review) {
            $commentId = $review->comment_ID;

            if (empty($commentId)) {
                continue;
            }

            $rating = (int) get_comment_meta($commentId, 'rating', true);

            if ($rating > 0 && $rating <= 5) {
                $ratingSummary[$rating] = isset($ratingSummary[$rating])
                    ? $ratingSummary[$rating] + 1
                    : 1;

                $reviewsData[$rating][] = [
                    'rating'       => $rating,
                    'comment_data' => Utility::jsonEncodeDecode($review),
                    'user_data'    => Utility::getUserInfo($review->user_id),
                ];
            }
        }

        $ratingLabels = [
            1 => 'Rated 1 out of 5 stars',
            2 => 'Rated 2 out of 5 stars',
            3 => 'Rated 3 out of 5 stars',
            4 => 'Rated 4 out of 5 stars',
            5 => 'Rated 5 out of 5 stars',
        ];

        $response = [];
        foreach ($ratingLabels as $ratingNumber => $ratingLabel) {
            $response[] = [
                'slug'    => "rated_{$ratingNumber}_out_of_5",
                'name'    => $ratingLabel,
                'total'   => $ratingSummary[$ratingNumber] ?? 0,
                'reviews' => $reviewsData[$ratingNumber] ?? [],
            ];
        }

        return [
            'response'    => $response,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    public function getTotalsReviews()
    {
        global $wpdb;

        $ratingCounts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT CAST(cm.meta_value AS UNSIGNED) AS rating, COUNT(*) AS total
                FROM {$wpdb->comments} c
                INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID
                INNER JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
                WHERE p.post_type = %s AND c.comment_approved = %s AND cm.meta_key = %s
                GROUP BY rating",
                'product',
                '1',
                'rating'
            ),
            OBJECT_K
        );

        $totals = [];

        for ($rating = 1; $rating <= 5; ++$rating) {
            $totals[] = ['rating' => $rating, 'count' => (int) ($ratingCounts[$rating]->total ?? 0)];
        }

        return [
            'response'    => $totals,
            'payload'     => [],
            'status_code' => 200,
        ];
    }

    public function createProductReview(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'product_id'     => ['required', 'integer'],
            'reviewer'       => ['required', 'string'],
            'reviewer_email' => ['required', 'email'],
            'rating'         => ['required', 'integer'],
            'review'         => ['required', 'string'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $rating = (int) $fieldMappings['rating'];

        if ($rating < 1 || $rating > 5) {
            return [
                'response'    => __('Rating must be between 1 and 5.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 400,
            ];
        }

        $product = wc_get_product((int) $fieldMappings['product_id']);

        if (empty($product)) {
            return [
                'response'    => __('Product not found with the given ID.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        // Mapped review text comes from outside, so filter it like a guest comment, as wp_update_comment() does.
        $filterContent = !has_filter('pre_comment_content', 'wp_filter_kses');

        if ($filterContent) {
            add_filter('pre_comment_content', 'wp_filter_kses');
        }

        $commentData = wp_filter_comment(
            wp_slash(
                [
                    'comment_post_ID'      => $product->get_id(),
                    'comment_author'       => $fieldMappings['reviewer'],
                    'comment_author_email' => $fieldMappings['reviewer_email'],
                    'comment_author_url'   => '',
                    'comment_author_IP'    => '',
                    'comment_content'      => $fieldMappings['review'],
                    'comment_type'         => 'review',
                    'comment_approved'     => empty($fieldMappings['approved']) ? 0 : 1,
                ]
            )
        );

        if ($filterContent) {
            remove_filter('pre_comment_content', 'wp_filter_kses');
        }

        $commentId = wp_insert_comment($commentData);

        if (!$commentId) {
            return [
                'response'    => __('Failed to create review.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        update_comment_meta($commentId, 'rating', $rating);
        update_comment_meta($commentId, 'verified', empty($fieldMappings['verified']) ? 0 : 1);
        WC_Comments::clear_transients($product->get_id());

        return [
            'response'    => WooCommerceHelper::getReviewData(get_comment($commentId)),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function getReviewById(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = ['id' => ['required', 'integer']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $comment = $this->findReview($fieldMappings['id']);

        if (empty($comment)) {
            return [
                'response'    => __('Review not found.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        return [
            'response'    => WooCommerceHelper::getReviewData($comment),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function updateReview(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = [
            'review_id'      => ['required', 'integer'],
            'reviewer_email' => ['nullable', 'email'],
            'rating'         => ['nullable', 'integer'],
        ];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $reviewId = (int) $fieldMappings['review_id'];
        $review = $this->findReview($reviewId);

        if (empty($review)) {
            return [
                'response'    => __('Review not found.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        $commentFields = [
            'reviewer'       => 'comment_author',
            'reviewer_email' => 'comment_author_email',
            'review'         => 'comment_content',
        ];

        $updateData = [];

        foreach ($commentFields as $key => $commentField) {
            if (!empty($fieldMappings[$key])) {
                $updateData[$commentField] = $fieldMappings[$key];
            }
        }

        if (!empty($updateData)) {
            $result = wp_update_comment(wp_slash(array_merge(['comment_ID' => $reviewId], $updateData)), true);

            if ($result === false || is_wp_error($result)) {
                return [
                    'response'    => is_wp_error($result) ? $result->get_error_message() : __('Failed to update review.', 'bit-pi'),
                    'payload'     => $fieldMappings,
                    'status_code' => 500,
                ];
            }
        }

        if (isset($fieldMappings['rating']) && $fieldMappings['rating'] !== '') {
            update_comment_meta($reviewId, 'rating', (int) $fieldMappings['rating']);
            WC_Comments::clear_transients((int) $review->comment_post_ID);
        }

        if (!empty($fieldMappings['approved'])) {
            wp_set_comment_status($reviewId, 'approve');
        }

        return [
            'response'    => WooCommerceHelper::getReviewData(get_comment($reviewId)),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function approveReview(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = ['id' => ['required', 'integer']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $reviewId = (int) $fieldMappings['id'];

        if (empty($this->findReview($reviewId))) {
            return [
                'response'    => __('Review not found.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        if (wp_get_comment_status($reviewId) !== 'approved' && !wp_set_comment_status($reviewId, 'approve')) {
            return [
                'response'    => __('Failed to approve review.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => WooCommerceHelper::getReviewData(get_comment($reviewId)),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    public function deleteReview(): array
    {
        $fieldMappings = $this->nodeInfoProvider->getFieldMapData();

        $validationRules = ['review_id' => ['required', 'integer']];

        $validationErrors = IntegrationHelper::validateFieldMap($fieldMappings, $validationRules);

        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $reviewId = (int) $fieldMappings['review_id'];

        if (empty($this->findReview($reviewId))) {
            return [
                'response'    => __('Review not found.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 404,
            ];
        }

        if (!wp_delete_comment($reviewId, !empty($fieldMappings['force_delete']))) {
            return [
                'response'    => __('Failed to delete review.', 'bit-pi'),
                'payload'     => $fieldMappings,
                'status_code' => 500,
            ];
        }

        return [
            'response'    => __('Review deleted successfully.', 'bit-pi'),
            'payload'     => $fieldMappings,
            'status_code' => 200,
        ];
    }

    private function findReview($reviewId)
    {
        $comment = get_comment($reviewId);

        if (empty($comment) || $comment->comment_type !== 'review' || get_post_type($comment->comment_post_ID) !== 'product') {
            return;
        }

        return $comment;
    }
}
