<?php
class controller_news extends Controller {
    
	public function view($arguments) {
		@$id = implode('/', $arguments);
		if (empty($id)) return Error::set('Invalid id.');
		$newsModel = new news(ConnectionFactory::get('mongo'));
		$news = $newsModel->get($id);
		
		if (empty($news)) return Error::set('Invalid id.');
		
		$this->view['news'] = $news;
		$this->view['multiple'] = (count($news) > 1);
	}
    
    public function post($arguments) {
        if (!CheckAcl::can('postNews'))
            return Error::set('You can not post news!');
            
        $this->view['valid'] = true;
        
        if (!empty($arguments[0]) && $arguments[0] == 'save') {
            if (empty($_POST['title']) || empty($_POST['text']))
                return Error::set('All forms need to be filled out.');
                
            $news = new news(ConnectionFactory::get('mongo'));
            $news->create($_POST['title'], $_POST['text'], 
                (!empty($_POST['commentable']) ? $_POST['commentable'] : false));
                
            Error::set('Entry posted!', true);
            header('Location: ' . Url::format('/'));
        }
    }
    
    public function edit($arguments) {
        if (!CheckAcl::can('editNews'))
            return Error::set('You can not edit news!');
        if (empty($arguments) || empty($arguments[0]))
            return Error::set('News ID is required.');
        
        $news = new news(ConnectionFactory::get('mongo'));
        $post = $news->get($arguments[0], false, false);
        
        if (empty($post))
            return Error::set('Invalid news ID.');
        
        $this->view['valid'] = true;
        $this->view['post'] = $post;
        
        if (!empty($arguments[1]) && $arguments[1] == 'save') {
            if (empty($_POST['title']) || empty($_POST['text']))
                return Error::set('All forms need to be filled out.');
                
            $news = new news(ConnectionFactory::get('mongo'));
            $news->edit($arguments[0], $_POST['title'], $_POST['text'],
                (!empty($_POST['commentable']) ? $_POST['commentable'] : false));
            
            $this->view['post'] = $news->get($arguments[0], false, false);
            Error::set('Entry edited!', true);
        }
    }
    
    public function delete($arguments) {
        if (!CheckAcl::can('deleteNews'))
            return Error::set('You can not delete news!');
        if (empty($arguments) || empty($arguments[0]))
            return Error::set('News ID is required.');
        
        $news = new news(ConnectionFactory::get('mongo'));
        $post = $news->get($arguments[0], false, false);
        
        if (empty($post))
            return Error::set('Invalid news ID.');
        
        $news->delete($arguments[0]);
        Error::set('Entry deleted.', true);
        
        header('Location: ' . Url::format('/'));
    }
}
