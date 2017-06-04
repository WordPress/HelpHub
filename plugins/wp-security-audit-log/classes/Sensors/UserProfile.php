<?php

class WSAL_Sensors_UserProfile extends WSAL_AbstractSensor
{

    public function HookEvents()
    {
        add_action('admin_init', array($this, 'EventAdminInit'));
        add_action('user_register', array($this, 'EventUserRegister'));
        add_action('edit_user_profile_update', array($this, 'EventUserChanged'));
        add_action('personal_options_update', array($this, 'EventUserChanged'));
        add_action('delete_user', array($this, 'EventUserDeleted'));
        add_action('wpmu_delete_user', array($this, 'EventUserDeleted'));
        add_action('set_user_role', array($this, 'EventUserRoleChanged'), 10, 3);

        add_action('edit_user_profile', array($this, 'EventOpenProfile'), 10, 1);
    }
    
    protected $old_superadmins;
    
    public function EventAdminInit()
    {
        if ($this->IsMultisite()) {
            $this->old_superadmins = get_super_admins();
        }
    }
    
    public function EventUserRegister($user_id)
    {
        $user = get_userdata($user_id);
        $ismu = function_exists('is_multisite') && is_multisite();
        $event = $ismu ? 4012 : (is_user_logged_in() ? 4001 : 4000);
        $current_user = wp_get_current_user();
        $this->plugin->alerts->Trigger($event, array(
            'NewUserID' => $user_id,
            'UserChanger' => !empty($current_user) ? $current_user->user_login : '',
            'NewUserData' => (object)array(
                'Username' => $user->user_login,
                'FirstName' => $user->user_firstname,
                'LastName' => $user->user_lastname,
                'Email' => $user->user_email,
                'Roles' => is_array($user->roles) ? implode(', ', $user->roles) : $user->roles,
            ),
        ), true);
    }
    
    public function EventUserRoleChanged($user_id, $role, $oldRoles)
    {
        $user = get_userdata($user_id);
        $aBbpRoles = array('bbp_spectator', 'bbp_moderator', 'bbp_participant', 'bbp_keymaster', 'bbp_blocked');
        // remove any BBPress roles
        if (is_array($oldRoles)) {
            foreach ($oldRoles as $value) {
                if (in_array($value, $aBbpRoles)) {
                    if ($_POST['bbp-forums-role'] != $value) {
                        $current_user = wp_get_current_user();
                        $this->plugin->alerts->TriggerIf(4013, array(
                            'TargetUsername' => $user->user_login,
                            'OldRole' => ucfirst(substr($value, 4)),
                            'NewRole' => ucfirst(substr($_POST['bbp-forums-role'], 4)),
                            'UserChanger' => $current_user->user_login
                        ));
                    }
                }
            }
            $oldRoles = array_diff($oldRoles, $aBbpRoles);
        }
        $oldRole = count($oldRoles) ? implode(', ', $oldRoles) : '';
        $newRole = $role;
        if ($oldRole != $newRole) {
            $this->plugin->alerts->TriggerIf(4002, array(
                'TargetUserID' => $user_id,
                'TargetUsername' => $user->user_login,
                'OldRole' => $oldRole,
                'NewRole' => $newRole,
            ), array($this, 'MustNotContainUserChanges'));
        }
    }

    public function EventUserChanged($user_id)
    {
        $user = get_userdata($user_id);

        // password changed
        if (!empty($_REQUEST['pass1']) && !empty($_REQUEST['pass2'])) {
            if (trim($_REQUEST['pass1']) == trim($_REQUEST['pass2'])) {
                $event = $user_id == get_current_user_id() ? 4003 : 4004;
                $this->plugin->alerts->Trigger($event, array(
                    'TargetUserID' => $user_id,
                    'TargetUserData' => (object)array(
                        'Username' => $user->user_login,
                        'Roles' => is_array($user->roles) ? implode(', ', $user->roles) : $user->roles,
                    ),
                ));
            }
        }

        // email changed
        if (!empty($_REQUEST['email'])) {
            $oldEmail = $user->user_email;
            $newEmail = trim($_REQUEST['email']);
            if ($oldEmail != $newEmail) {
                $event = $user_id == get_current_user_id() ? 4005 : 4006;
                $this->plugin->alerts->Trigger($event, array(
                    'TargetUserID' => $user_id,
                    'TargetUsername' => $user->user_login,
                    'OldEmail' => $oldEmail,
                    'NewEmail' => $newEmail,
                ));
            }
        }
        
        if ($this->IsMultisite()) {
            $username = $user->user_login;
            $enabled = isset($_REQUEST['super_admin']);
            
            if ($user_id != get_current_user_id()) {
                // super admin enabled
                if ($enabled && !in_array($username, $this->old_superadmins)) {
                    $this->plugin->alerts->Trigger(4008, array(
                        'TargetUserID' => $user_id,
                        'TargetUsername' => $user->user_login,
                    ));
                }

                // super admin disabled
                if (!$enabled && in_array($username, $this->old_superadmins)) {
                    $this->plugin->alerts->Trigger(4009, array(
                        'TargetUserID' => $user_id,
                        'TargetUsername' => $user->user_login,
                    ));
                }
                
            }
        }
    }
    
    public function EventUserDeleted($user_id)
    {
        $user = get_userdata($user_id);
        $role = is_array($user->roles) ? implode(', ', $user->roles) : $user->roles;
        $this->plugin->alerts->TriggerIf(4007, array(
            'TargetUserID' => $user_id,
            'TargetUserData' => (object)array(
                'Username' => $user->user_login,
                'FirstName' => $user->user_firstname,
                'LastName' => $user->user_lastname,
                'Email' => $user->user_email,
                'Roles' => $role ? $role : 'none',
            ),
        ), array($this, 'MustNotContainCreateUser'));
    }

    public function EventOpenProfile($user)
    {
        if (!empty($user)) {
            $current_user = wp_get_current_user();
            if (!empty($current_user) && ($user->ID != $current_user->ID)) {
                $this->plugin->alerts->Trigger(4014, array(
                    'UserChanger' => $current_user->user_login,
                    'TargetUsername' => $user->user_login
                ));
            }
        }
    }
    
    public function MustNotContainCreateUser(WSAL_AlertManager $mgr)
    {
        return !$mgr->WillTrigger(4012);
    }
    
    public function MustNotContainUserChanges(WSAL_AlertManager $mgr)
    {
        return !(  $mgr->WillOrHasTriggered(4010)
                || $mgr->WillOrHasTriggered(4011)
                || $mgr->WillOrHasTriggered(4012)
                || $mgr->WillOrHasTriggered(4000)
                || $mgr->WillOrHasTriggered(4001)
            );
    }
}
