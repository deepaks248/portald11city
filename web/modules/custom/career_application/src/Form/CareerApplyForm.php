<?php

namespace Drupal\career_application\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\Constraints\File as FileConstraint;


class CareerApplyForm extends FormBase
{
    private const THEME_KEY = '#theme';
    private const TYPE_KEY = '#type';
    private const TITLE_KEY = '#title';
    private const ATTRIBUTES_KEY = '#attributes';
    private const REQUIRED_KEY = '#required';
    private const INPUT_CLASSES = [
        'form-input',
        'w-full',
        'rounded-md',
        'border',
        'border-gray-300',
        'focus:border-yellow-500',
        'focus:ring-yellow-500',
        'text-gray-700',
        'text-base',
        'p-2.5',
    ];

    public function getFormId()
    {
        return 'career_apply_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL)
    {

        $form[self::THEME_KEY] = 'career_apply_form';

        $form['nid'] = [
            self::TYPE_KEY => 'hidden',
            '#value' => $nid,
        ];

        $form['first_name'] = $this->buildInputField('textfield', 'First Name');

        $form['last_name'] = $this->buildInputField('textfield', 'Last Name');

        $form['email'] = $this->buildInputField('email', 'Email');

        $form['mobile'] = $this->buildInputField('tel', 'Mobile Number');

        $form['gender'] = [
            self::TYPE_KEY => 'select',
            self::TITLE_KEY => $this->t('Gender'),
            self::ATTRIBUTES_KEY => [
                'class' => [
                    'form-select',
                    'rounded-md',
                    'border',
                    'border-gray-300',
                    'focus:border-yellow-500',
                    'focus:ring-yellow-500',
                    'text-gray-700',
                    'text-base',
                    'p-2.5'
                ],
            ],
            '#options' => [
                'male' => $this->t('Male'),
                'female' => $this->t('Female'),
                'other' => $this->t('Other'),
            ],
            self::REQUIRED_KEY => TRUE,
        ];

        $form['resume'] = [
            self::TYPE_KEY => 'managed_file',
            self::TITLE_KEY => $this->t('Upload your CV*'),
            self::REQUIRED_KEY => TRUE,
            '#upload_location' => 'public://resumes/',
            '#constraints' => [
                new \Symfony\Component\Validator\Constraints\File([
                    'maxSize' => '5M',
                    'mimeTypes' => [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ],
                    'mimeTypesMessage' => $this->t('Please upload a valid PDF or Word document.'),
                ]),
            ],
        ];


        $form['submit'] = [
            self::TYPE_KEY => 'submit',
            '#value' => $this->t('Apply Now'),
        ];

        $form['#attached']['library'][] = 'career_application/career-apply-form-library';
        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $values = $form_state->getValues();
        $uid = \Drupal::currentUser()->id();
        $resume_fid = $values['resume'][0] ?? NULL;

        // Set file permanent
        if ($resume_fid) {
            $file = \Drupal\file\Entity\File::load($resume_fid);
            if ($file) {
                $file->setPermanent();
                $file->save();
            }
        }

        \Drupal::database()->insert('career_applications')->fields([
            'uid' => $uid,
            'nid' => $values['nid'],
            'first_name' => $values['first_name'],
            'last_name' => $values['last_name'],
            'email' => $values['email'],
            'mobile' => $values['mobile'],
            'gender' => $values['gender'],
            'resume_fid' => $resume_fid,
            'applied' => \Drupal::time()->getCurrentTime(),
        ])->execute();

        $form_state->setRedirect('career_application.success_page');
    }

    private function buildInputField(string $type, string $title): array
    {
        return [
            self::TYPE_KEY => $type,
            self::TITLE_KEY => $this->t($title),
            self::ATTRIBUTES_KEY => [
                'placeholder' => $this->t($title),
                'class' => self::INPUT_CLASSES,
            ],
            self::REQUIRED_KEY => TRUE,
        ];
    }
}
