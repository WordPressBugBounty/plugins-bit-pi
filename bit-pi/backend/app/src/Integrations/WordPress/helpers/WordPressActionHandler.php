<?php

namespace BitApps\Pi\src\Integrations\WordPress\helpers;

use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\WordPress\WordPressServices;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}


class WordPressActionHandler
{
    public static function executeAction(NodeInfoProvider $nodeInfoProvider)
    {
        $wordPressService = new WordPressServices($nodeInfoProvider);

        $machineSlug = $nodeInfoProvider->getMachineSlug();

        switch ($machineSlug) {
            // === User Management ===
            case 'createNewUser':
                return $wordPressService->createUser();

                break;

            case 'updateUser':
                return $wordPressService->updateUser();

                break;

            case 'deleteExitingUser':
                return $wordPressService->deleteUser();

                break;

                // === User Retrieval ===
            case 'getAllUsers':
                return $wordPressService->getAllUsers();

                break;

            case 'getAllUsersByRole':
                return $wordPressService->getAllUsersByRole();

                break;

            case 'getUserById':
                return $wordPressService->getUserById();

                break;

            case 'getUserByEmail':
                return $wordPressService->getUserByEmail();

                break;

            case 'getUserByField':
                return $wordPressService->getUserByField();

                break;

                // === User Metadata ===
            case 'getUserMetadata':
                return $wordPressService->getUserMetadata();

                break;

            case 'getUserMetadataByMetaKey':
                return $wordPressService->getUserMetadataByMetaKey();

                break;

            case 'updateUserMetadata':
                return $wordPressService->updateUserMetadata();

                break;

                // === Role Management ===
            case 'createRole':
                return $wordPressService->createRole();

                break;

            case 'deleteRole':
                return $wordPressService->deleteRole();

                break;

            case 'getAllRoles':
                return $wordPressService->getAllRoles();

                break;

            case 'addUserRoles':
                return $wordPressService->manageUserRole();

                break;

            case 'removeUserRole':
                return $wordPressService->manageUserRole(true);

                break;

            case 'updateUserRole':
                return $wordPressService->manageUserRole(false, true);

                break;

                // === Capabilities Management ===
            case 'getAllCapabilities':
                return $wordPressService->getAllCapabilities();

                break;

            case 'getRoleCapabilities':
                return $wordPressService->getRoleCapabilities();

                break;

            case 'addRoleCapabilities':
                return $wordPressService->manageRoleCapabilities();

                break;

            case 'removeRoleCapabilities':
                return $wordPressService->manageRoleCapabilities(true);

                break;

            case 'getUserCapabilities':
                return $wordPressService->getUserCapabilities();

                break;

            case 'addUserCapabilities':
                return $wordPressService->manageUserCapabilities();

                break;

            case 'removeUserCapabilities':
                return $wordPressService->manageUserCapabilities(true);

                break;

                // === Post Management ===
            case 'getAllPosts':
                return $wordPressService->getAllPosts();

                break;

            case 'getPostById':
                return $wordPressService->getPostById();

                break;

            case 'getPostsByPostType':
                return $wordPressService->getPostsByPostType();

                break;

            case 'getPostsByMetadata':
                return $wordPressService->getPostsByMetadata();

                break;

            case 'getPostMetadata':
                return $wordPressService->getPostMetadata();

                break;

            case 'getPostMetadataByMetaKey':
                return $wordPressService->getPostMetadataByMetaKey();

                break;

            case 'getPostPermalink':
                return $wordPressService->getPostPermalink();

                break;

            case 'getPostContent':
                return $wordPressService->getPostContent();

                break;

            case 'getPostExcerpt':
                return $wordPressService->getPostExcerpt();

                break;

            case 'getPostStatus':
                return $wordPressService->getPostStatus();

                break;

            case 'createNewPost':
                return $wordPressService->createNewPost();

                break;

            case 'updateExistingPost':
                return $wordPressService->updateExistingPost();

                break;

            case 'updatePostStatus':
                return $wordPressService->updatePostStatus();

                break;

            case 'deleteExistingPost':
                return $wordPressService->deleteExistingPost();

                break;

                // === Comments Management ===
            case 'getAllPostComments':
                return $wordPressService->getAllPostComments();

                break;

            case 'getPostComments':
                return $wordPressService->getPostComments();

                break;

            case 'getUserComments':
                return $wordPressService->getUserComments();

                break;

            case 'getUserCommentsByEmail':
                return $wordPressService->getUserCommentsByEmail();

                break;

            case 'getCommentMetadata':
                return $wordPressService->getCommentMetadata();

                break;

            case 'getCommentMetadataByMetaKey':
                return $wordPressService->getCommentMetadataByMetaKey();

                break;

            case 'createNewComment':
                return $wordPressService->createNewComment();

                break;

            case 'replyToComment':
                return $wordPressService->replyToComment();

                break;

            case 'deleteExistingComment':
                return $wordPressService->deleteExistingComment();

                break;

                // === Post Type Management ===
            case 'getAllPostTypes':
                return $wordPressService->getAllPostTypes();

                break;

            case 'getPostType':
                return $wordPressService->getPostType();

                break;

            case 'registerPostType':
                return $wordPressService->registerPostType();

                break;

            case 'unregisterPostType':
                return $wordPressService->unregisterPostType();

                break;

            case 'addPostTypeFeatures':
                return $wordPressService->addPostTypeFeatures();

                break;

                // === Post Tag Management ===
            case 'createPostTag':
                return $wordPressService->createTermByTax('post_tag');

                break;

            case 'updatePostTag':
                return $wordPressService->updateTermByTax('post_tag');

                break;

            case 'deletePostTag':
                return $wordPressService->deleteTermByTax('post_tag');

                break;

            case 'getAllPostTags':
                return $wordPressService->getAllTerms('post_tag');

                break;

            case 'getPostTag':
                return $wordPressService->getTermById('post_tag');

                break;

            case 'addTaxonomyToPost':
                return $wordPressService->addTaxonomyToPost();

                break;

            case 'removeTaxonomyFromPost':
                return $wordPressService->removeTaxonomyFromPost();

                break;

            case 'addTagsToPost':
                return $wordPressService->addTagsToPost();

                break;

            case 'removeTagsFromPost':
                return $wordPressService->removeTagsFromPost();

                break;

                // === Media Management ===
            case 'addNewImage':
                return $wordPressService->addNewImage();

                break;

            case 'deleteMedia':
                return $wordPressService->deleteMedia();

                break;

            case 'renameMedia':
                return $wordPressService->renameMedia();

                break;

            case 'getAllMedia':
                return $wordPressService->getAllMedia();

                break;

            case 'getMediaByTitle':
                return $wordPressService->getMediaByTitle();

                break;

            case 'getMediaById':
                return $wordPressService->getMediaById();

                break;

                // === Taxonomy Management ===
            case 'getAllTaxonomies':
                return $wordPressService->getAllTaxonomies();

                break;

            case 'getTaxonomy':
                return $wordPressService->getTaxonomy();

                break;

            case 'registerTaxonomy':
                return $wordPressService->registerTaxonomy();

                break;

            case 'unregisterTaxonomy':
                return $wordPressService->unregisterTaxonomy();

                break;

                // === Term Management ===
            case 'getAllTerms':
                return $wordPressService->getAllTerms();

                break;

            case 'getTerm':
                return $wordPressService->getTerm();

                break;

            case 'getTermByField':
                return $wordPressService->getTermByField();

                break;

            case 'getTermByTaxonomy':
                return $wordPressService->getTermByTaxonomy();

                break;

            case 'createNewTerm':
                return $wordPressService->createNewTerm();

                break;

            case 'updateTerm':
                return $wordPressService->updateTerm();

                break;

            case 'termDelete':
                return $wordPressService->deleteTerm();

                break;

                // === Category Management ===
            case 'createCategory':
                return $wordPressService->createTermByTax('category');

                break;

            case 'updateCategory':
                return $wordPressService->updateTermByTax('category');

                break;

            case 'deleteCategory':
                return $wordPressService->deleteTermByTax('category');

                break;

            case 'addCategoryToPost':
                return $wordPressService->addCategoryToPost();

                break;

            case 'getAllCategories':
                return $wordPressService->getAllTerms('category');

                break;

            case 'getCategory':
                return $wordPressService->getTermById('category');

                break;

                // === Product Tag Management ===
            case 'createProductTag':
                return $wordPressService->createTermByTax('product_tag');

                break;

            case 'updateProductTag':
                return $wordPressService->updateTermByTax('product_tag');

                break;

            case 'deleteProductTag':
                return $wordPressService->deleteTermByTax('product_tag');

                break;

            case 'getAllProductTags':
                return $wordPressService->getAllTerms('product_tag');

                break;

            case 'getProductTag':
                return $wordPressService->getTermById('product_tag');

                break;

                // === Product Category Management ===
            case 'createProductCategory':
                return $wordPressService->createTermByTax('product_cat');

                break;

            case 'updateProductCategory':
                return $wordPressService->updateTermByTax('product_cat');

                break;

            case 'deleteProductCategory':
                return $wordPressService->deleteTermByTax('product_cat');

                break;

            case 'getAllProductCategories':
                return $wordPressService->getAllTerms('product_cat');

                break;

            case 'getProductCategory':
                return $wordPressService->getTermById('product_cat');

                break;

                // === Product Type Management ===
            case 'createProductType':
                return $wordPressService->createTermByTax('product_type');

                break;

            case 'updateProductType':
                return $wordPressService->updateTermByTax('product_type');

                break;

            case 'deleteProductType':
                return $wordPressService->deleteTermByTax('product_type');

                break;

            case 'getAllProductTypes':
                return $wordPressService->getAllTerms('product_type');

                break;

            case 'getProductType':
                return $wordPressService->getTermById('product_type');

                break;

                // === Plugin Management ===
            case 'checkPluginActivationStatus':
                return $wordPressService->checkPluginActivationStatus();

                break;

            case 'activatePlugin':
                return $wordPressService->activatePlugin();

                break;
        }
    }
}
