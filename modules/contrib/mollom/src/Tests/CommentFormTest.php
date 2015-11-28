<?php
/**
 * @file contains Drupal\mollom\Tests\CommentFormTestCase
 */

namespace Drupal\mollom\Tests;
use Drupal\mollom\Entity\FormInterface;

/**
 * Check that the comment submission form can be protected.
 * @group mollom
 */
class CommentFormTest extends MollomTestBase {

  public static $modules = ['dblog', 'mollom', 'node', 'comment', 'mollom_test_server', 'mollom_test'];

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));

    $this->webUser = $this->drupalCreateUser(array('create article content', 'access comments', 'post comments', 'skip comment approval'));
    $this->node = $this->drupalCreateNode(array('type' => 'article', 'uid' => $this->webUser->uid));

    $this->addCommentsToNode('article');
  }

  /**
   * Make sure that the comment submission form can be unprotected.
   */
  function testUnprotectedCommentForm() {
    // Request the comment reply form. There should be no CAPTCHA.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node/' . $this->node->id());
    $this->assertNoCaptchaField();
    $this->assertNoPrivacyLink();

    // Preview a comment that is 'spam' and make sure there is still no CAPTCHA.
    $this->drupalPostForm(NULL, ['comment_body[0][value]' => 'spam'], t('Preview'));
    $this->assertNoCaptchaField();
    $this->assertNoPrivacyLink();

    // Save the comment and make sure it appears.
    $this->drupalPostForm(NULL, [], t('Save'));
    $this->assertRaw('<p>spam</p>', t('A comment that is known to be spam appears on the screen after it is submitted.'));
  }

  /**
   * Make sure that the comment submission form can be protected by captcha only.
   */
  function testCaptchaProtectedCommentForm() {
    // Enable Mollom CAPTCHA protection for comments.
    $this->drupalLogin($this->adminUser);
    $this->setProtectionUI('comment_node_article_form', FormInterface::MOLLOM_MODE_CAPTCHA);
    $this->drupalLogout();

    // Request the comment reply form. There should be a CAPTCHA form.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node/' . $this->node->id());
    $this->assertCaptchaField();
    $this->assertResponseIDInForm('captchaId');
    $this->assertNoPrivacyLink();

    // Try to submit an incorrect answer for the CAPTCHA, without value for
    // required field.
    $this->postIncorrectCaptcha(NULL, [], t('Preview'));
    $this->assertText(t('Comment field is required.'));
    $this->assertResponseIDInForm('captchaId', TRUE);
    $this->assertNoPrivacyLink();

    // Try to submit a correct answer for the CAPTCHA, still without required
    // field value.
    $this->postCorrectCaptcha(NULL, [], t('Preview'));
    $this->assertText(t('Comment field is required.'));
    $captchaId = $this->assertResponseIDInForm('captchaId', TRUE);
    $this->assertNoPrivacyLink();

    // Finally, we should be able to submit a comment.
    $this->drupalPostForm(NULL, array('comment_body[0][value]' => 'spam'), t('Save'));
    $this->assertText(t('Your comment has been posted.'));
    $this->assertRaw('<p>spam</p>', t('Spam comment could be posted with correct CAPTCHA.'));
    $cids = $this->loadCommentsBySubject('spam');
    $this->assertMollomData('comment', reset($cids), 'captchaId', $captchaId);

    // Verify we can solve the CAPTCHA directly.
    $this->resetResponseID();
    $value = 'some more spam';
    $this->drupalGet('node/' . $this->node->id());
    $this->assertCaptchaField();
    $captchaId = $this->assertResponseIDInForm('captchaId');
    $edit = [
      'comment_body[0][value]' => $value,
      self::CAPTCHA_INPUT => 'correct',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText(t('Your comment has been posted.'));
    $cids = $this->loadCommentsBySubject($value);
    $this->assertMollomData('comment', reset($cids), 'captchaId', $captchaId);
  }

  /**
   * Make sure that the comment submission form can be fully protected.
   */
  function testTextAnalysisProtectedCommentForm() {
    // Enable Mollom text-classification for comments.
    $this->drupalLogin($this->adminUser);
    $this->setProtectionUI('comment_node_article_form');
    $this->drupalLogout();

    // Request the comment reply form.  Initially, there should be no CAPTCHA.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('node/'. $this->node->id());
    $this->assertNoCaptchaField();
    $this->assertPrivacyLink();

    // Try to save a comment that is 'unsure' and make sure there is a CAPTCHA.
    $edit = [
      'comment_body[0][value]' => 'unsure',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertCaptchaField();
    $contentId = $this->assertResponseIDInForm('contentId');
    $this->assertPrivacyLink();

    // Try to submit the form by solving the CAPTCHA incorrectly. At this point,
    // the submission should be blocked and a new CAPTCHA generated, but only if
    // the comment is still neither ham or spam.
    $this->postIncorrectCaptcha(NULL, array(), t('Save'));
    $this->assertCaptchaField();
    $captchaId = $this->assertResponseIDInForm('captchaId');
    $this->assertPrivacyLink();

    // Correctly solving the CAPTCHA should accept the form submission.
    $this->postCorrectCaptcha(NULL, array(), t('Save'));
    $this->assertRaw('<p>' . $edit['comment_body[0][value]'] . '</p>', t('A comment that may contain spam was found.'));
    $cids = $this->loadCommentsBySubject($edit['comment_body[0][value]']);
    $this->assertMollomData('comment', reset($cids), 'contentId', $contentId);

    // Try to save a new 'spam' comment; it should be discarded, with no CAPTCHA
    // appearing on the page.
    $this->resetResponseID();
    $this->drupalGet('node/' . $this->node->id());
    $this->assertPrivacyLink();
    $original_number_of_comments = $this->getCommentCount($this->node->id());
    $this->assertSpamSubmit(NULL, array('comment_body[0][value]'), array(), t('Save'));
    $contentId = $this->assertResponseIDInForm('contentId');
    $this->assertCommentCount($this->node->id(), $original_number_of_comments);
    $this->assertPrivacyLink();

    // Try to save again; it should be discarded, with no CAPTCHA.
    $this->assertSpamSubmit(NULL, array('comment_body[0][value]'), array(), t('Save'));
    $contentId = $this->assertResponseIDInForm('contentId');
    $this->assertCommentCount($this->node->id(), $original_number_of_comments);
    $this->assertPrivacyLink();

    // Save a new 'ham' comment.
    $this->resetResponseID();
    $this->drupalGet('node/' . $this->node->id());
    $this->assertPrivacyLink();
    $original_number_of_comments = $this->getCommentCount($this->node->id());
    $this->assertHamSubmit(NULL, array('comment_body[0][value]'), array(), t('Save'));
    $this->assertRaw('<p>ham</p>', t('A comment that is known to be ham appears on the screen after it is submitted.'));
    $this->assertCommentCount($this->node->id(), $original_number_of_comments + 1);
    $cids = $this->loadCommentsBySubject('ham');
    $this->assertMollomData('comment', reset($cids));
  }

  /**
   * Return the number of comments for a node of the given node ID.  We
   * can't use comment_num_all() here, because that is statically cached
   * and therefore will not work correctly with the SimpleTest browser.
   */
  private function getCommentCount($nid) {
    return \Drupal::database()->query('SELECT comment_count FROM {comment_entity_statistics} WHERE entity_id = :nid and entity_type=:type and field_name=:field',
      [
        ':nid' => $nid,
        ':type' => 'node',
        ':field' => 'comment',
      ]
    )->fetchField();
  }

  /**
   * Test that the number of comments for a node matches an expected value.
   *
   * @param $nid
   *   A node ID
   * @param $expected
   *   An integer with the expected number of comments for the node.
   * @param $message
   *   An optional string with the message to be used in the assertion.
   */
  protected function assertCommentCount($nid, $expected, $message = '') {
    $actual = $this->getCommentCount($nid);
    if (!$message) {
      $message = t('Node @nid has @actual comment(s), expected @expected.', array('@nid' => $nid, '@actual' => $actual, '@expected' => $expected));
    }
    $this->assertEqual($actual, $expected, $message);
  }
}

