<?php
class sfNestedCommentTools
{
  static public function sendEmail($mailer, array $params)
  {
    $message = $mailer->compose();
    $message->setSubject($params['subject']);
    $message->setTo($params['to']);
    $message->setFrom($params['from']);
    if (isset ($params['message']['html'])) $message->setBody($params['message']['html'], 'text/html');
    if (isset ($params['message']['text'])) $message->addPart($params['message']['text'], 'text/plain');
    $mailer->send($message);
  }

  public static function addRecursiveComments($comments, $renderer, $nested = true)
  {
    foreach ($comments as $comment)
    {
      $renderer->addChild($comment);
      if ($comment->hasChildren() && $nested)
      {
        $depth = sfConfig::get('app_sfNestedComment_nested_depth', 3);
        $r = ($comment->getTreeLevel() >= $depth) ? $renderer : $renderer[$comment->getId()];
        self::addRecursiveComments($comment->getApprovedChildren(), $r, $nested);
      }
    }
  }

  public static function createCommentsRenderer($comments, $nested = true)
  {
    $renderer = new sfNestedCommentsRenderer();
    self::addRecursiveComments($comments, $renderer, $nested);
    return $renderer;
  }

  public static function getCommentableObject(BaseObject $commentModel)
  {
    return PropelQuery::from($commentModel->getCommentableModel())->findPk($commentModel->getCommentableId());
  }

  public static function createCommentForm($commentableObject)
  {
    $comment = new sfNestedComment();
    $form = new sfNestedCommentFrontForm($comment);
    $form->setDefault('commentable_model', get_class($commentableObject));
    $form->setDefault('commentable_id', $commentableObject->getPrimaryKey());
    return $form;
  }

  public static function getComments(BaseObject $commentableObject, sfWebRequest $request)
  {
    $page = null;
    $comments = array();

    if (sfConfig::get('app_sfNestedComment_paging', true))
    {
      $page = $request->getParameter('comment-page', 1);
    }
    if (sfConfig::get('app_sfNestedComment_nested', true))
    {
      $comments = $commentableObject->getApprovedCommentsLevel1($page, sfConfig::get('app_sfNestedComment_max_per_page', 5));
    }
    else
    {
      $comments = $commentableObject->getApprovedComments($page, sfConfig::get('app_sfNestedComment_max_per_page', 5));
    }
    return $comments;
  }

  //taken from sfPropelActAsCommentableBehavior
  static public function clean($text)
  {
    $allowed_html_tags = sfConfig::get('app_sfNestedComment_allowed_tags', array());
    spl_autoload_register(array('HTMLPurifier_Bootstrap', 'autoload'));
    $config = HTMLPurifier_Config::createDefault();
    $config->set('HTML.Doctype', 'XHTML 1.0 Strict');
    $config->set('HTML.Allowed', implode(',', array_keys($allowed_html_tags)));

    if (isset($allowed_html_tags['a']))
    {
      $config->set('HTML.AllowedAttributes', 'a.href');
      $config->set('AutoFormat.Linkify', true);
    }

    if (isset($allowed_html_tags['p']))
    {
      $config->set('AutoFormat.AutoParagraph', true);
    }

    $purifier = new HTMLPurifier($config);
    return str_replace('<a href', '<a rel="nofollow" href', $purifier->purify($text));
  }

  //http://brenelz.com/blog/creating-an-ellipsis-in-php/
  public static function ellipsis($text, $max=25, $append='&hellip;')
  {
    if (strlen($text) <= $max) return $text;
    $out = substr($text,0,$max);
    if (strpos($text,' ') === FALSE) return $out.$append;
    return preg_replace('/\w+$/','',$out).$append;
  }

}
