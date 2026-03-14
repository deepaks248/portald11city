<?php

namespace Drupal\career_application\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Controller for career application pages.
 */
class CareerApplyController extends ControllerBase
{
    private const THEME_KEY = '#theme';

    /**
     * Show list of jobs the user applied for.
     */
    public function userApplications()
    {
        $uid = $this->currentUser()->id();
        $applications = [];

        $result = \Drupal::database()->select('career_applications', 'ca')
            ->fields('ca', ['nid', 'applied', 'first_name', 'last_name'])
            ->condition('uid', $uid)
            ->orderBy('applied', 'DESC')
            ->execute();

        foreach ($result as $record) {
            $node = \Drupal\node\Entity\Node::load($record->nid);
            if ($node && $node->bundle() === 'careers') {
                $applications[] = [
                    'title' => $node->label(),
                    'experience' => $node->get('field_experience')->value ?? '',
                    'location' => $node->get('field_job_location')->value ?? '',
                    'nid' => $node->id(),
                    'applied' => $record->applied,
                ];
            }
        }

        return [
            self::THEME_KEY => 'career_applications_list',
            '#applications' => $applications,
            // '#attached' => ['library' => ['career_application/your-custom-styles']],
        ];
    }


    public function success()
    {
        return [
            self::THEME_KEY => 'career_application_success',
            '#title' => $this->t('Application Submitted'),
        ];
    }

    public function applicationDetails($nid)
    {
        $uid = $this->currentUser()->id();

        $record = \Drupal::database()->select('career_applications', 'ca')
            ->fields('ca')
            ->condition('uid', $uid)
            ->condition('nid', $nid)
            ->execute()
            ->fetchObject();

        $node = \Drupal\node\Entity\Node::load($nid);

        if (!$record || !$node) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        // Load resume file URL
        $resume_url = NULL;
        if (!empty($record->resume_fid)) {
            $file = \Drupal\file\Entity\File::load($record->resume_fid);
            if ($file) {
                $resume_url = \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri());
            }
        }



        return [
            self::THEME_KEY => 'career_application_detail',
            '#node' => $node,
            '#application' => $record,
            '#resume_url' => $resume_url,
        ];
    }
}
