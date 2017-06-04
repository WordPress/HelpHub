<?php
/**
 * Support for WooCommerce Plugin
 */
class WSAL_Sensors_WooCommerce extends WSAL_AbstractSensor
{
    protected $_OldPost = null;
    protected $_OldLink = null;
    protected $_OldCats = null;
    protected $_OldData = null;
    protected $_OldStockStatus = null;
    protected $_OldFileNames = array();
    protected $_OldFileUrls = array();

    public function HookEvents()
    {
        if (current_user_can("edit_posts")) {
            add_action('admin_init', array($this, 'EventAdminInit'));
        }
        add_action('post_updated', array($this, 'EventChanged'), 10, 3);
        add_action('delete_post', array($this, 'EventDeleted'), 10, 1);
        add_action('wp_trash_post', array($this, 'EventTrashed'), 10, 1);
        add_action('untrash_post', array($this, 'EventUntrashed'));

        add_action('create_product_cat', array($this, 'EventCategoryCreation'), 10, 1);
        // add_action('edit_product_cat', array($this, 'EventCategoryChanged'), 10, 1);
    }

    public function EventAdminInit()
    {
        // load old data, if applicable
        $this->RetrieveOldData();
        $this->CheckSettingsChange();
    }

    protected function RetrieveOldData()
    {
        if (isset($_POST) && isset($_POST['post_ID'])
            && !(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            && !(isset($_POST['action']) && $_POST['action'] == 'autosave')
        ) {
            $postID = intval($_POST['post_ID']);
            $this->_OldPost = get_post($postID);
            $this->_OldLink = get_post_permalink($postID, false, true);
            $this->_OldCats = $this->GetProductCategories($this->_OldPost);
            $this->_OldData = $this->GetProductData($this->_OldPost);
            $this->_OldStockStatus = get_post_meta($postID, '_stock_status', true);

            $oldDownloadableFiles  = get_post_meta($postID, '_downloadable_files', true);
            if (!empty($oldDownloadableFiles)) {
                foreach ($oldDownloadableFiles as $file) {
                    array_push($this->_OldFileNames, $file['name']);
                    array_push($this->_OldFileUrls, $file['file']);
                }
            }
        }
    }

    public function EventChanged($post_ID, $newpost, $oldpost)
    {
        if ($this->CheckWooCommerce($oldpost)) {
            $changes = 0 + $this->EventCreation($oldpost, $newpost);
            if (!$changes) {
                // Change Categories
                $changes = $this->CheckCategoriesChange($this->_OldCats, $this->GetProductCategories($newpost), $oldpost, $newpost);
            }
            if (!$changes) {
                // Change Short description, Text, URL, Product Data, Date, Visibility, etc.
                $changes = 0
                    + $this->CheckShortDescriptionChange($oldpost, $newpost)
                    + $this->CheckTextChange($oldpost, $newpost)
                    + $this->CheckProductDataChange($this->_OldData, $newpost)
                    + $this->CheckDateChange($oldpost, $newpost)
                    + $this->CheckVisibilityChange($oldpost)
                    + $this->CheckStatusChange($oldpost, $newpost)
                    + $this->CheckPriceChange($oldpost)
                    + $this->CheckSKUChange($oldpost)
                    + $this->CheckStockStatusChange($oldpost)
                    + $this->CheckStockQuantityChange($oldpost)
                    + $this->CheckTypeChange($oldpost, $newpost)
                    + $this->CheckWeightChange($oldpost)
                    + $this->CheckDimensionsChange($oldpost)
                    + $this->CheckDownloadableFileChange($oldpost)
                ;
            }
            if (!$changes) {
                // Change Permalink
                $changes = $this->CheckPermalinkChange($this->_OldLink, get_post_permalink($post_ID, false, true), $newpost);
                if (!$changes) {
                    // if no one of the above changes happen
                    $this->CheckModifyChange($oldpost, $newpost);
                }
            }
        }
    }

    /**
     * Trigger events 9000, 9001
     */
    private function EventCreation($old_post, $new_post)
    {
        $original = isset($_POST['original_post_status']) ? $_POST['original_post_status'] : '';
        if ($original == 'draft' && $new_post->post_status == 'draft') {
            return 0;
        }
        if ($old_post->post_status == 'draft' || $original == 'auto-draft') {
            if ($old_post->post_type == 'product') {
                $editorLink = $this->GetEditorLink($new_post);
                if ($new_post->post_status == 'draft') {
                    $this->plugin->alerts->Trigger(9000, array(
                        'ProductTitle' => $new_post->post_title,
                        $editorLink['name'] => $editorLink['value']
                    ));
                    return 1;
                } else if ($new_post->post_status == 'publish') {
                    $this->plugin->alerts->Trigger(9001, array(
                        'ProductTitle' => $new_post->post_title,
                        'ProductUrl' => get_post_permalink($new_post->ID),
                        $editorLink['name'] => $editorLink['value']
                    ));
                    return 1;
                }
            }
        }
        return 0;
    }

    /**
     * Trigger events 9002
     */
    public function EventCategoryCreation($term_id = null)
    {
        $term = get_term($term_id);
        if (!empty($term)) {
            $this->plugin->alerts->Trigger(9002, array(
                'CategoryName' => $term->name,
                'Slug' => $term->slug
            ));
        }
    }

    /**
     * Not implemented
     */
    public function EventCategoryChanged($term_id = null)
    {
        $old_term = get_term($term_id);
        if (isset($_POST['taxonomy'])) {
            // new $term in $_POST
        }
    }

    /**
     * Trigger events 9003
     */
    protected function CheckCategoriesChange($oldCats, $newCats, $oldpost, $newpost)
    {
        if ($newpost->post_status == 'trash' || $oldpost->post_status == 'trash') {
            return 0;
        }
        $oldCats = is_array($oldCats) ? implode(', ', $oldCats) : $oldCats;
        $newCats = is_array($newCats) ? implode(', ', $newCats) : $newCats;
        if ($oldCats != $newCats) {
            $editorLink = $this->GetEditorLink($newpost);
            $this->plugin->alerts->Trigger(9003, array(
                'ProductTitle' => $newpost->post_title,
                'OldCategories' => $oldCats ? $oldCats : 'no categories',
                'NewCategories' => $newCats ? $newCats : 'no categories',
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9004
     */
    protected function CheckShortDescriptionChange($oldpost, $newpost)
    {
        if ($oldpost->post_status == 'auto-draft') {
            return 0;
        }
        if ($oldpost->post_excerpt != $newpost->post_excerpt) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9004, array(
                'ProductTitle' => $oldpost->post_title,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9005
     */
    protected function CheckTextChange($oldpost, $newpost)
    {
        if ($oldpost->post_status == 'auto-draft') {
            return 0;
        }
        if ($oldpost->post_content != $newpost->post_content) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9005, array(
                'ProductTitle' => $oldpost->post_title,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9006
     */
    protected function CheckPermalinkChange($oldLink, $newLink, $post)
    {
        if (($oldLink && $newLink) && ($oldLink != $newLink)) {
            $editorLink = $this->GetEditorLink($post);
            $this->plugin->alerts->Trigger(9006, array(
                'ProductTitle' => $post->post_title,
                'OldUrl' => $oldLink,
                'NewUrl' => $newLink,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9007
     */
    protected function CheckProductDataChange($oldData, $post)
    {
        if (isset($_POST['product-type'])) {
            $oldData = is_array($oldData) ? implode(', ', $oldData) : $oldData;
            $newData = $_POST['product-type'];
            if ($oldData != $newData) {
                $editorLink = $this->GetEditorLink($post);
                $this->plugin->alerts->Trigger(9007, array(
                    'ProductTitle' => $post->post_title,
                    $editorLink['name'] => $editorLink['value']
                ));
                return 1;
            }
        }
        return 0;
    }

    /**
     * Trigger events 9008
     */
    protected function CheckDateChange($oldpost, $newpost)
    {
        if ($oldpost->post_status == 'draft' || $oldpost->post_status == 'auto-draft') {
            return 0;
        }
        $from = strtotime($oldpost->post_date);
        $to = strtotime($newpost->post_date);
        if ($from != $to) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9008, array(
                'ProductTitle' => $oldpost->post_title,
                'OldDate' => $oldpost->post_date,
                'NewDate' => $newpost->post_date,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9009
     */
    protected function CheckVisibilityChange($oldpost)
    {
        $oldVisibility = isset($_POST['hidden_post_visibility']) ? $_POST['hidden_post_visibility'] : null;
        $newVisibility = isset($_POST['visibility']) ? $_POST['visibility'] : null;
        
        if ($oldVisibility == 'password') {
            $oldVisibility = __('Password Protected', 'wp-security-audit-log');
        } else {
            $oldVisibility = ucfirst($oldVisibility);
        }
        
        if ($newVisibility == 'password') {
            $newVisibility = __('Password Protected', 'wp-security-audit-log');
        } else {
            $newVisibility = ucfirst($newVisibility);
        }
        
        if (($oldVisibility && $newVisibility) && ($oldVisibility != $newVisibility)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9009, array(
                'ProductTitle' => $oldpost->post_title,
                'OldVisibility' => $oldVisibility,
                'NewVisibility' => $newVisibility,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9010, 9011
     */
    protected function CheckModifyChange($oldpost, $newpost)
    {
        if ($newpost->post_status == 'trash') {
            return 0;
        }
        $editorLink = $this->GetEditorLink($oldpost);
        if ($oldpost->post_status == 'publish') {
            $this->plugin->alerts->Trigger(9010, array(
                'ProductTitle' => $oldpost->post_title,
                'ProductUrl' => get_post_permalink($oldpost->ID),
                $editorLink['name'] => $editorLink['value']
            ));
        } else if ($oldpost->post_status == 'draft') {
            $this->plugin->alerts->Trigger(9011, array(
                'ProductTitle' => $oldpost->post_title,
                $editorLink['name'] => $editorLink['value']
            ));
        }
    }

    /**
     * Moved to Trash 9012
     */
    public function EventTrashed($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckWooCommerce($post)) {
            $this->plugin->alerts->Trigger(9012, array(
                'ProductTitle' => $post->post_title,
                'ProductUrl' => get_post_permalink($post->ID)
            ));
        }
    }

    /**
     * Permanently deleted 9013
     */
    public function EventDeleted($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckWooCommerce($post)) {
            $this->plugin->alerts->Trigger(9013, array(
                'ProductTitle' => $post->post_title
            ));
        }
    }

    /**
     * Restored from Trash 9014
     */
    public function EventUntrashed($post_id)
    {
        $post = get_post($post_id);
        if ($this->CheckWooCommerce($post)) {
            $editorLink = $this->GetEditorLink($post);
            $this->plugin->alerts->Trigger(9014, array(
                'ProductTitle' => $post->post_title,
                $editorLink['name'] => $editorLink['value']
            ));
        }
    }

    /**
     * Trigger events 9015
     */
    protected function CheckStatusChange($oldpost, $newpost)
    {
        if ($oldpost->post_status == 'draft' || $oldpost->post_status == 'auto-draft') {
            return 0;
        }
        if ($oldpost->post_status != $newpost->post_status) {
            if ($oldpost->post_status != 'trash' && $newpost->post_status != 'trash') {
                $editorLink = $this->GetEditorLink($oldpost);
                $this->plugin->alerts->Trigger(9015, array(
                    'ProductTitle' => $oldpost->post_title,
                    'OldStatus' => $oldpost->post_status,
                    'NewStatus' => $newpost->post_status,
                    $editorLink['name'] => $editorLink['value']
                ));
                return 1;
            }
        }
        return 0;
    }

    /**
     * Trigger events 9016
     */
    protected function CheckPriceChange($oldpost)
    {
        $result = 0;
        $oldPrice = get_post_meta($oldpost->ID, '_regular_price', true);
        $oldSalePrice = get_post_meta($oldpost->ID, '_sale_price', true);
        $newPrice = isset($_POST['_regular_price']) ? $_POST['_regular_price'] : null;
        $newSalePrice = isset($_POST['_sale_price']) ? $_POST['_sale_price'] : null;

        if (($newPrice) && ($oldPrice != $newPrice)) {
            $result = $this->EventPrice($oldpost, 'Regular price', $oldPrice, $newPrice);
        }
        if (($newSalePrice) && ($oldSalePrice != $newSalePrice)) {
            $result = $this->EventPrice($oldpost, 'Sale price', $oldSalePrice, $newSalePrice);
        }
        return $result;
    }

    /**
     * Group the Price changes in one function
     */
    private function EventPrice($oldpost, $type, $oldPrice, $newPrice)
    {
        $currency = $this->GetCurrencySymbol($this->GetConfig('currency'));
        $editorLink = $this->GetEditorLink($oldpost);
        $this->plugin->alerts->Trigger(9016, array(
            'ProductTitle' => $oldpost->post_title,
            'PriceType' => $type,
            'OldPrice' => (!empty($oldPrice) ? $currency.$oldPrice : 0),
            'NewPrice' => $currency.$newPrice,
            $editorLink['name'] => $editorLink['value']
        ));
        return 1;
    }

    /**
     * Trigger events 9017
     */
    protected function CheckSKUChange($oldpost)
    {
        $oldSku = get_post_meta($oldpost->ID, '_sku', true);
        $newSku = isset($_POST['_sku']) ? $_POST['_sku'] : null;

        if (($newSku) && ($oldSku != $newSku)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9017, array(
                'ProductTitle' => $oldpost->post_title,
                'OldSku' => (!empty($oldSku) ? $oldSku : 0),
                'NewSku' => $newSku,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9018
     */
    protected function CheckStockStatusChange($oldpost)
    {
        $oldStatus = $this->_OldStockStatus;
        $newStatus = isset($_POST['_stock_status']) ? $_POST['_stock_status'] : null;

        if (($oldStatus && $newStatus) && ($oldStatus != $newStatus)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9018, array(
                'ProductTitle' => $oldpost->post_title,
                'OldStatus' => $this->GetStockStatusName($oldStatus),
                'NewStatus' => $this->GetStockStatusName($newStatus),
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9019
     */
    protected function CheckStockQuantityChange($oldpost)
    {
        $oldValue  = get_post_meta($oldpost->ID, '_stock', true);
        $newValue = isset($_POST['_stock']) ? $_POST['_stock'] : null;

        if (($newValue) && ($oldValue != $newValue)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9019, array(
                'ProductTitle' => $oldpost->post_title,
                'OldValue' => (!empty($oldValue) ? $oldValue : 0),
                'NewValue' => $newValue,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9020
     */
    protected function CheckTypeChange($oldpost, $newpost)
    {
        $result = 0;
        if ($oldpost->post_status != 'trash' && $newpost->post_status != 'trash') {
            $oldVirtual  = get_post_meta($oldpost->ID, '_virtual', true);
            $newVirtual = isset($_POST['_virtual']) ? 'yes' : 'no';
            $oldDownloadable  = get_post_meta($oldpost->ID, '_downloadable', true);
            $newDownloadable = isset($_POST['_downloadable']) ? 'yes' : 'no';

            if (($oldVirtual && $newVirtual) && ($oldVirtual != $newVirtual)) {
                $type = ($newVirtual == 'no') ? 'Non Virtual' : 'Virtual';
                $result = $this->EventType($oldpost, $type);
            }
            if (($oldDownloadable && $newDownloadable) && ($oldDownloadable != $newDownloadable)) {
                $type = ($newDownloadable == 'no') ? 'Non Downloadable' : 'Downloadable';
                $result = $this->EventType($oldpost, $type);
            }
        }
        return $result;
    }

    /**
     * Group the Type changes in one function
     */
    private function EventType($oldpost, $type)
    {
        $editorLink = $this->GetEditorLink($oldpost);
        $this->plugin->alerts->Trigger(9020, array(
            'ProductTitle' => $oldpost->post_title,
            'Type' => $type,
            $editorLink['name'] => $editorLink['value']
        ));
        return 1;
    }

    /**
     * Trigger events 9021
     */
    protected function CheckWeightChange($oldpost)
    {
        $oldWeight  = get_post_meta($oldpost->ID, '_weight', true);
        $newWeight = isset($_POST['_weight']) ? $_POST['_weight'] : null;

        if (($newWeight) && ($oldWeight != $newWeight)) {
            $editorLink = $this->GetEditorLink($oldpost);
            $this->plugin->alerts->Trigger(9021, array(
                'ProductTitle' => $oldpost->post_title,
                'OldWeight' => (!empty($oldWeight) ? $oldWeight : 0),
                'NewWeight' => $newWeight,
                $editorLink['name'] => $editorLink['value']
            ));
            return 1;
        }
        return 0;
    }

    /**
     * Trigger events 9022
     */
    protected function CheckDimensionsChange($oldpost)
    {
        $result = 0;
        $oldLength  = get_post_meta($oldpost->ID, '_length', true);
        $newLength = isset($_POST['_length']) ? $_POST['_length'] : null;
        $oldWidth  = get_post_meta($oldpost->ID, '_width', true);
        $newWidth = isset($_POST['_width']) ? $_POST['_width'] : null;
        $oldHeight  = get_post_meta($oldpost->ID, '_height', true);
        $newHeight = isset($_POST['_height']) ? $_POST['_height'] : null;

        if (($newLength) && ($oldLength != $newLength)) {
            $result = $this->EventDimension($oldpost, 'Length', $oldLength, $newLength);
        }
        if (($newWidth) && ($oldWidth != $newWidth)) {
            $result = $this->EventDimension($oldpost, 'Width', $oldWidth, $newWidth);
        }
        if (($newHeight) && ($oldHeight != $newHeight)) {
            $result = $this->EventDimension($oldpost, 'Height', $oldHeight, $newHeight);
        }
        return $result;
    }

    /**
     * Group the Dimension changes in one function
     */
    private function EventDimension($oldpost, $type, $oldDimension, $newDimension)
    {
        $dimension_unit = $this->GetConfig('dimension_unit');
        $editorLink = $this->GetEditorLink($oldpost);
        $this->plugin->alerts->Trigger(9022, array(
            'ProductTitle' => $oldpost->post_title,
            'DimensionType' => $type,
            'OldDimension' => (!empty($oldDimension) ? $dimension_unit.' '.$oldDimension : 0),
            'NewDimension' => $dimension_unit.' '.$newDimension,
            $editorLink['name'] => $editorLink['value']
        ));
        return 1;
    }

    /**
     * Trigger events 9023, 9024, 9025, 9026
     */
    protected function CheckDownloadableFileChange($oldpost)
    {
        $result = 0;
        $isUrlChanged = false;
        $isNameChanged = false;
        $newFileNames = !empty($_POST['_wc_file_names']) ? $_POST['_wc_file_names'] : array();
        $newFileUrls = !empty($_POST['_wc_file_urls']) ? $_POST['_wc_file_urls'] : array();
        $editorLink = $this->GetEditorLink($oldpost);

        $addedUrls = array_diff($newFileUrls, $this->_OldFileUrls);
        // Added files to the product
        if (count($addedUrls) > 0) {
            // if the file has only changed URL
            if (count($newFileUrls) == count($this->_OldFileUrls)) {
                $isUrlChanged = true;
            } else {
                foreach ($addedUrls as $key => $url) {
                    $this->plugin->alerts->Trigger(9023, array(
                        'ProductTitle' => $oldpost->post_title,
                        'FileName' => $newFileNames[$key],
                        'FileUrl' => $url,
                        $editorLink['name'] => $editorLink['value']
                    ));
                }
                $result = 1;
            }
        }

        $removedUrls = array_diff($this->_OldFileUrls, $newFileUrls);
        // Removed files from the product
        if (count($removedUrls) > 0) {
            // if the file has only changed URL
            if (count($newFileUrls) == count($this->_OldFileUrls)) {
                $isUrlChanged = true;
            } else {
                foreach ($removedUrls as $key => $url) {
                    $this->plugin->alerts->Trigger(9024, array(
                        'ProductTitle' => $oldpost->post_title,
                        'FileName' => $this->_OldFileNames[$key],
                        'FileUrl' => $url,
                        $editorLink['name'] => $editorLink['value']
                    ));
                }
                $result = 1;
            }
        }

        $addedNames = array_diff($newFileNames, $this->_OldFileNames);
        if (count($addedNames) > 0) {
            // if the file has only changed Name
            if (count($newFileNames) == count($this->_OldFileNames)) {
                foreach ($addedNames as $key => $name) {
                    $this->plugin->alerts->Trigger(9025, array(
                        'ProductTitle' => $oldpost->post_title,
                        'OldName' => $this->_OldFileNames[$key],
                        'NewName' => $name,
                        $editorLink['name'] => $editorLink['value']
                    ));
                }
                $result = 1;
            }
        }

        if ($isUrlChanged) {
            foreach ($addedUrls as $key => $url) {
                $this->plugin->alerts->Trigger(9026, array(
                    'ProductTitle' => $oldpost->post_title,
                    'FileName' => $newFileNames[$key],
                    'OldUrl' => $removedUrls[$key],
                    'NewUrl' => $url,
                    $editorLink['name'] => $editorLink['value']
                ));
            }
            $result = 1;
        }
        return $result;
    }

    /**
     * Trigger events Settings: 9027, 9028, 9029, 9030, 9031, 9032, 9033
     */
    protected function CheckSettingsChange()
    {
        if (isset($_GET['page']) && $_GET['page'] == 'wc-settings') {
            if (isset($_GET['tab']) && $_GET['tab'] == 'products') {
                if (isset($_POST['woocommerce_weight_unit'])) {
                    $oldUnit = $this->GetConfig('weight_unit');
                    $newUnit = $_POST['woocommerce_weight_unit'];
                    if ($oldUnit != $newUnit) {
                        $this->plugin->alerts->Trigger(9027, array(
                            'OldUnit' => $oldUnit,
                            'NewUnit' => $newUnit
                        ));
                    }
                }
                if (isset($_POST['woocommerce_dimension_unit'])) {
                    $oldUnit = $this->GetConfig('dimension_unit');
                    $newUnit = $_POST['woocommerce_dimension_unit'];
                    if ($oldUnit != $newUnit) {
                        $this->plugin->alerts->Trigger(9028, array(
                            'OldUnit' => $oldUnit,
                            'NewUnit' => $newUnit
                        ));
                    }
                }
            } else if (isset($_GET['tab']) && $_GET['tab'] == 'checkout') {
                if (!empty($_POST)) {
                    $oldEnableCoupons = $this->GetConfig('enable_coupons');
                    $newEnableCoupons = isset($_POST['woocommerce_enable_coupons']) ? 'yes' : 'no';
                    if ($oldEnableCoupons != $newEnableCoupons) {
                        $status = ($newEnableCoupons == 'yes') ? 'Enabled' : 'Disabled';
                        $this->plugin->alerts->Trigger(9032, array(
                            'Status' => $status,
                        ));
                    }
                    $oldEnableGuestCheckout = $this->GetConfig('enable_guest_checkout');
                    $newEnableGuestCheckout = isset($_POST['woocommerce_enable_guest_checkout']) ? 'yes' : 'no';
                    if ($oldEnableGuestCheckout != $newEnableGuestCheckout) {
                        $status = ($newEnableGuestCheckout == 'yes') ? 'Enabled' : 'Disabled';
                        $this->plugin->alerts->Trigger(9033, array(
                            'Status' => $status,
                        ));
                    }
                }
            } else {
                if (isset($_POST['woocommerce_default_country'])) {
                    $oldLocation = $this->GetConfig('default_country');
                    $newLocation = $_POST['woocommerce_default_country'];
                    if ($oldLocation != $newLocation) {
                        $this->plugin->alerts->Trigger(9029, array(
                            'OldLocation' => $oldLocation,
                            'NewLocation' => $newLocation
                        ));
                    }
                    $oldCalcTaxes = $this->GetConfig('calc_taxes');
                    $newCalcTaxes = isset($_POST['woocommerce_calc_taxes']) ? 'yes' : 'no';
                    if ($oldCalcTaxes != $newCalcTaxes) {
                        $status = ($newCalcTaxes == 'yes') ? 'Enabled' : 'Disabled';
                        $this->plugin->alerts->Trigger(9030, array(
                            'Status' => $status,
                        ));
                    }
                }
                if (isset($_POST['woocommerce_currency'])) {
                    $oldCurrency = $this->GetConfig('currency');
                    $newCurrency = $_POST['woocommerce_currency'];
                    if ($oldCurrency != $newCurrency) {
                        $this->plugin->alerts->Trigger(9031, array(
                            'OldCurrency' => $oldCurrency,
                            'NewCurrency' => $newCurrency
                        ));
                    }
                }
            }
        }
    }

    private function GetStockStatusName($slug)
    {
        if ($slug == 'instock') {
            return __('In stock', 'wp-security-audit-log');
        } else if ($slug == 'outofstock') {
            return __('Out of stock', 'wp-security-audit-log');
        }
    }

    protected function GetProductCategories($post)
    {
        return wp_get_post_terms($post->ID, 'product_cat', array("fields" => "names"));
    }

    protected function GetProductData($post)
    {
        return wp_get_post_terms($post->ID, 'product_type', array("fields" => "names"));
    }

    /**
     * Get the config setting
     * @param string $option_name
     */
    private function GetConfig($option_name)
    {
        $fn = $this->IsMultisite() ? 'get_site_option' : 'get_option';
        return $fn('woocommerce_' . $option_name);
    }

    private function CheckWooCommerce($post)
    {
        switch ($post->post_type) {
            case 'product':
                return true;
            default:
                return false;
        }
    }

    private function GetEditorLink($post)
    {
        $name = 'EditorLinkProduct';
        $value = get_edit_post_link($post->ID);
        $aLink = array(
            'name' => $name,
            'value' => $value,
        );
        return $aLink;
    }

    /**
     * Get Currency symbol.
     * @param string $currency (default: '')
     * @return string
     */
    private function GetCurrencySymbol($currency = '')
    {
        $symbols = array(
            'AED' => '&#x62f;.&#x625;','AFN' => '&#x60b;','ALL' => 'L','AMD' => 'AMD','ANG' => '&fnof;','AOA' => 'Kz','ARS' => '&#36;',
            'AUD' => '&#36;','AWG' => '&fnof;','AZN' => 'AZN','BAM' => 'KM','BBD' => '&#36;','BDT' => '&#2547;&nbsp;','BGN' => '&#1083;&#1074;.',
            'BHD' => '.&#x62f;.&#x628;','BIF' => 'Fr','BMD' => '&#36;','BND' => '&#36;','BOB' => 'Bs.','BRL' => '&#82;&#36;','BSD' => '&#36;',
            'BTC' => '&#3647;','BTN' => 'Nu.','BWP' => 'P','BYR' => 'Br','BZD' => '&#36;','CAD' => '&#36;','CDF' => 'Fr','CHF' => '&#67;&#72;&#70;',
            'CLP' => '&#36;','CNY' => '&yen;','COP' => '&#36;','CRC' => '&#x20a1;','CUC' => '&#36;','CUP' => '&#36;','CVE' => '&#36;',
            'CZK' => '&#75;&#269;','DJF' => 'Fr','DKK' => 'DKK','DOP' => 'RD&#36;','DZD' => '&#x62f;.&#x62c;','EGP' => 'EGP','ERN' => 'Nfk',
            'ETB' => 'Br','EUR' => '&euro;','FJD' => '&#36;','FKP' => '&pound;','GBP' => '&pound;','GEL' => '&#x10da;','GGP' => '&pound;',
            'GHS' => '&#x20b5;','GIP' => '&pound;','GMD' => 'D','GNF' => 'Fr','GTQ' => 'Q','GYD' => '&#36;','HKD' => '&#36;','HNL' => 'L',
            'HRK' => 'Kn','HTG' => 'G','HUF' => '&#70;&#116;','IDR' => 'Rp','ILS' => '&#8362;','IMP' => '&pound;','INR' => '&#8377;',
            'IQD' => '&#x639;.&#x62f;','IRR' => '&#xfdfc;','ISK' => 'kr.','JEP' => '&pound;','JMD' => '&#36;','JOD' => '&#x62f;.&#x627;',
            'JPY' => '&yen;','KES' => 'KSh','KGS' => '&#x441;&#x43e;&#x43c;','KHR' => '&#x17db;','KMF' => 'Fr','KPW' => '&#x20a9;','KRW' => '&#8361;',
            'KWD' => '&#x62f;.&#x643;','KYD' => '&#36;','KZT' => 'KZT','LAK' => '&#8365;','LBP' => '&#x644;.&#x644;','LKR' => '&#xdbb;&#xdd4;',
            'LRD' => '&#36;','LSL' => 'L','LYD' => '&#x644;.&#x62f;','MAD' => '&#x62f;. &#x645;.','MAD' => '&#x62f;.&#x645;.','MDL' => 'L','MGA' => 'Ar',
            'MKD' => '&#x434;&#x435;&#x43d;','MMK' => 'Ks','MNT' => '&#x20ae;','MOP' => 'P','MRO' => 'UM','MUR' => '&#x20a8;','MVR' => '.&#x783;',
            'MWK' => 'MK','MXN' => '&#36;','MYR' => '&#82;&#77;','MZN' => 'MT','NAD' => '&#36;','NGN' => '&#8358;','NIO' => 'C&#36;',
            'NOK' => '&#107;&#114;','NPR' => '&#8360;','NZD' => '&#36;','OMR' => '&#x631;.&#x639;.','PAB' => 'B/.','PEN' => 'S/.',
            'PGK' => 'K','PHP' => '&#8369;','PKR' => '&#8360;','PLN' => '&#122;&#322;','PRB' => '&#x440;.','PYG' => '&#8370;','QAR' => '&#x631;.&#x642;',
            'RMB' => '&yen;','RON' => 'lei','RSD' => '&#x434;&#x438;&#x43d;.','RUB' => '&#8381;','RWF' => 'Fr','SAR' => '&#x631;.&#x633;',
            'SBD' => '&#36;','SCR' => '&#x20a8;','SDG' => '&#x62c;.&#x633;.','SEK' => '&#107;&#114;','SGD' => '&#36;','SHP' => '&pound;','SLL' => 'Le',
            'SOS' => 'Sh','SRD' => '&#36;','SSP' => '&pound;','STD' => 'Db','SYP' => '&#x644;.&#x633;','SZL' => 'L','THB' => '&#3647;',
            'TJS' => '&#x405;&#x41c;','TMT' => 'm','TND' => '&#x62f;.&#x62a;','TOP' => 'T&#36;','TRY' => '&#8378;','TTD' => '&#36;',
            'TWD' => '&#78;&#84;&#36;','TZS' => 'Sh','UAH' => '&#8372;','UGX' => 'UGX','USD' => '&#36;','UYU' => '&#36;','UZS' => 'UZS',
            'VEF' => 'Bs F','VND' => '&#8363;','VUV' => 'Vt','WST' => 'T','XAF' => 'Fr','XCD' => '&#36;','XOF' => 'Fr','XPF' => 'Fr',
            'YER' => '&#xfdfc;','ZAR' => '&#82;','ZMW' => 'ZK',
        );
        $currency_symbol = isset($symbols[$currency]) ? $symbols[ $currency ] : '';

        return $currency_symbol;
    }
}
