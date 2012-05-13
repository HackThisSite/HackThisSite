<?php
class controller_user extends Controller {
    
    public function view($arguments) {
        if (empty($arguments[0])) 
            return Error::set('Username is required.');

        $username = $arguments[0];
        
        $users = new users(ConnectionFactory::get('mongo'));
        $userInfo = $users->get($username);
        
        if (empty($userInfo)) return Error::set('User not found.');
        
        $this->view['valid'] = true;
        $this->view['username'] = $username;
        $this->view['user'] = $userInfo;
        
        $irc = new irc(ConnectionFactory::get('redis'));
        $this->view['onIrc'] = $irc->isOnline($username);
        $this->view['onSite'] = apc_exists('user_' . $username);
        
        $articles = new articles(ConnectionFactory::get('mongo'));
        $lectures = new lectures(ConnectionFactory::get('mongo'));
        
        $this->view['articles'] = $articles->getForUser($this->view['user']['_id']);
        $this->view['lectures'] = $lectures->getForUser($username);
        
        Layout::set('title', $username . '\'s profile');
    }
    
    public function settings($arguments) {
        if (!Session::isLoggedIn())
            return Error::set('You are not logged in!');
        
        $user = new users(ConnectionFactory::get('mongo'));
        
        $this->view['valid'] = true;
        $this->view['user'] = $user->get(Session::getVar('username'));
        $this->view['secure'] = (!empty($_SERVER['SSL_CLIENT_RAW_CERT']) ? true : false);
        
        if ($this->view['secure']) 
            $this->view['clientSSLKey'] = certs::getKey($_SERVER['SSL_CLIENT_RAW_CERT']);
        
        if (!empty($arguments[0]) && $arguments[0] == 'save') {
            if (!empty($_POST['oldpassword']) && !empty($_POST['password'])) {
                $old = $user->hash($_POST['oldpassword'], $this->view['user']['username']);
                
                if ($old != $this->view['user']['password'])
                    return Error::set('Previous password is invalid.');
            }
            
            $username = (!empty($_POST['username']) ? $_POST['username'] : null);
            $password = (!empty($_POST['password']) ? $_POST['password'] : null);
            $email = (!empty($_POST['email']) ? $_POST['email'] : null);
            $hideEmail = (!empty($_POST['hideEmail']) ? true : false);
            
            $error = $user->edit(Session::getVar('_id'), $username, 
                $password, $email, $hideEmail, null);
            if (is_string($error)) return Error::set($error);
            
            $this->view['user'] = $user->get(Session::getVar('username'));
            Error::set('User profile saved.', true);
        }
        
        if (!empty($arguments[0]) && $arguments[0] == 'saveAuth') {
            $password = (!empty($_POST['passwordAuth']) ? true : false);
            $certificate = (!empty($_POST['certificateAuth']) ? true : false);
            $certAndPass = (!empty($_POST['certAndPassAuth']) ? true : false);
            $autoauth = (!empty($_POST['autoAuth']) ? true : false);
            
            $return = $user->changeAuth(Session::getVar('_id'), $password, 
                $certificate, $certAndPass, $autoauth);
            
            if (is_string($return)) return Error::set($return);
            $this->view['user'] = $user->get(Session::getVar('username'));
        }
        
        Layout::set('title', 'Settings');
    }
    
    public function rmCert() {
        if (!Session::isLoggedIn())
            return Error::set('You are not logged in!');
        if (empty($_POST['hash']))
            return Error::set('No certificate hash was found.');
        
        $certs = new certs(ConnectionFactory::get('redis'));
        $cert = $certs->get($_POST['hash'], false);
        
        if ($cert == null)
            return Error::set('Invalid certificate hash.');
        
        if (substr($cert, 0, strpos($cert, ':')) != Session::getVar('_id'))
            return Error::set('You are not allowed to remove this certificate.');
        
        $users = new users(ConnectionFactory::get('mongo'));
        $users->removeCert(Session::getVar('_id'), $_POST['hash']);
        $certs->removeCert($_POST['hash']);
        
        header('Location: ' . Url::format('/user/settings'));
    }
    
    public function viewCert() {
        if (!Session::isLoggedIn())
            return Error::set('You are not logged in!');
        if (empty($_POST['hash']))
            return Error::set('No certificate hash was found.');
        
        $certs = new certs(ConnectionFactory::get('redis'));
        $cert = $certs->get($_POST['hash'], false);
        
        if ($cert == null)
            return Error::set('Invalid certificate hash.');
        if (substr($cert, 0, strpos($cert, ':')) != Session::getVar('_id'))
            return Error::set('You are not allowed to view this certificate.');
        
        $this->view['cert'] = substr($cert, strpos($cert, ':') + 1);
    }
    
    public function login() {
        $username = (empty($_POST['username']) ? null : $_POST['username']);
        $password = (empty($_POST['password']) ? null : $_POST['password']);
        
        $users = new users(ConnectionFactory::get('mongo'));
        $good = $users->authenticate($username, $password);
        
        if (!isset($_POST['username']) || !isset($_POST['password'])) return;
        if (is_string($good)) return Error::set($good);
        
        $logs = new logs(ConnectionFactory::get('mongo'), ConnectionFactory::get('redis'));
        $logs->login($good['_id']);
        
        header('Location: ' . Url::format('/'));
    }
    
    public function logout() {
        Session::destroy();
        header('Location: ' . Url::format('/'));
    }
    
    public function register($arguments) {
        if (Session::isLoggedIn())
            return Error::set('You can\'t register if you\'re logged in!');
        
        $this->view['valid'] = true;
        
        if (!empty($arguments) && $arguments[0] == 'save') {
            if (empty($_POST['username']) || empty($_POST['password']) || empty($_POST['email']))
                return Error::set('All forms are required.');
            
            $users = new users(ConnectionFactory::get('mongo'));
            $hideEmail = (empty($_POST['hideEmail']) ? false : true);
            $created = $users->create($_POST['username'], $_POST['password'], 
                $_POST['email'], $hideEmail, null);
            if (is_string($created)) return Error::set($created);
            
            $users->authenticate($_POST['username'], $_POST['password']);
            header('Location: ' . Url::format('/'));
        }
    }
    
    public function addkey($arguments) {
        if (!Session::isLoggedIn()) return Error::set('Please login to add keys.');
        if (empty($_POST['csr'])) return Error::set('No CSR found.');
        
        $certs = new certs(ConnectionFactory::get('redis'));
        $output = $certs->create($_POST['csr']);
        
        if (!$output) return Error::set('Invalid CSR.');
        
        $users = new users(ConnectionFactory::get('mongo'));
        
        $check = $certs->preAdd($output);
        if (is_string($check))return Error::set($check);
        
        $check = $users->preAdd(Session::getVar('_id'), $certs->getKey($output));
        if (is_string($check))return Error::set($check);
        
        $certs->add($output);
        $users->addCert(Session::getVar('_id'), $certs->getKey($output));
        
        $this->view['valid'] = true;
        $this->view['certificate'] = $output;
    }
    
    public function link($arguments) {
        if (!Session::isLoggedIn()) return Error::set('Please login.');
        
        $irc = new irc(ConnectionFactory::get('redis'));
        $username = Session::getVar('username');
        
        $this->view['valid'] = true;
        $this->view['pending'] = $nicks = $irc->getPending($username);
        $this->view['nicks'] = $goodNicks = $irc->getNicks($username);
        
        if (!empty($arguments[0]) && $arguments[0] == 'add') {
            if (!isset($nicks[$arguments[1]])) return Error::set('Invalid nick id.');
            
            $irc->addNick($username, $nicks[$arguments[1]]);
            $this->view['pending'] = $irc->getPending($username);
            $this->view['nicks'] = $irc->getNicks($username);
        } else if (!empty($arguments[0]) && $arguments[0] == 'delP') {
            if (!isset($nicks[$arguments[1]])) return Error::set('Invalid nick id.');
            
            $irc->delNick($username, $nicks[$arguments[1]]);
            $this->view['pending'] = $irc->getPending($username);
        } else if (!empty($arguments[0]) && $arguments[0] == 'delA') {
            if (!isset($goodNicks[$arguments[1]])) return Error::set('Invalid nick id.');
            
            $irc->delAcceptedNick($username, $goodNicks[$arguments[1]]);
            $this->view['nicks'] = $irc->getPending($username);
        }
    }
    
    public function notes() {
        if (!CheckAcl::can('postNotes')) return Error::set('You are not allowed to post notes.');
        if (empty($_POST['userId'])) return Error::set('No user id was found.');
        if (empty($_POST['note'])) return Error::set('No note text was found.');
        
        $users = new users(ConnectionFactory::get('mongo'));
        $return = $users->addNote($_POST['userId'], $_POST['note']);
        
        if (is_string($return)) return Error::set($return);
        
        Error::set('Note posted.', true);
        
        if (!empty($_SERVER['HTTP_REFERER'])) header('Location: ' . Url::format($_SERVER['HTTP_REFERER']));
    }
    
    public function admin() {
        if (!CheckAcl::can('adminUsers')) return Error::set('You are not allowed to admin users.');
        if (empty($_POST['userId'])) return Error::set('No user id was found.');
        
        $user = new users(ConnectionFactory::get('mongo'));
        $userInfo = $user->get($_POST['userId'], false, true);
        
        if (empty($_POST['status']) && $userInfo['status'] == $user::ACCT_LOCKED) {
            $user->setStatus($_POST['userId'], $user::ACCT_OPEN);
        } else if (!empty($_POST['status']) && $_POST['status'] == 'locked' && $userInfo['status'] == $user::ACCT_OPEN) {
            $user->setStatus($_POST['userId'], $user::ACCT_LOCKED);
        }
        
        if (empty($_POST['group']) || !in_array($_POST['group'], acl::$acls))
            return Error::set('Invalid group.');
        
        if ($_POST['group'] != $userInfo['group']) {
            $user->setGroup($_POST['userId'], $_POST['group']);
        }
        
        header('Location: ' . Url::format('/user/view/' . $userInfo['username']));
    }
    
    public function forceLogout() {
        if (!CheckAcl::can('forceLogout')) return Error::set('You are not allowed to force logout a user.');
        if (empty($_POST['username'])) return Error::set('No username was found.');
        if (!apc_exists('user_' . $_POST['username'])) return Error::set('This user is already logged out.');
        
        Session::forceLogout($_POST['username'], apc_fetch('user_' . $_POST['username']));
        
        header('Location: ' . Url::format('/user/view/' . $_POST['username']));
    }
    
    public function logs() {
        if (!Session::isLoggedIn()) return Error::set('You are not logged in!');
        $this->view['valid'] = true;
        
        $logs = new logs(ConnectionFactory::get('mongo'), ConnectionFactory::get('redis'));
        $this->view['logins'] = $logs->getLogins(Session::getVar('_id'));
        $this->view['activity'] = $logs->getActivity(Session::getVar('_id'));
    }
    
    public function connections() {
        if (!Session::isLoggedIn()) return Error::set('You are not logged in!');
        $user = new users(ConnectionFactory::get('mongo'));
        $userInfo = $user->get(Session::getVar('_id'), false, true);
        
        if (empty($userInfo['connections'])) 
            return Error::set('You have no connections!');
        
        $this->view['valid'] = true;
    }
    
}
