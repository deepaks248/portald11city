<?php

namespace Drupal\career_application\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Validator\Constraints\File as FileConstraint;


class CareerApplyForm extends FormBase
{

    public function getFormId()
    {
        return 'career_apply_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL)
    {

        $form['#theme'] = 'career_apply_form';

        $form['nid'] = [
            '#type' => 'hidden',
            '#value' => $nid,
        ];

        $form['first_name'] = [
            '#type' => 'textfield',
            // '#title' => $this->t('First Name'),
            '#attributes' => [
                'placeholder' => $this->t('First Name'),
                'class' => [
                    'form-input',
                    'w-full',
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
            '#required' => TRUE,
        ];

        $form['last_name'] = [
            '#type' => 'textfield',
            // '#title' => $this->t('Last Name'),
            '#attributes' => [
                'placeholder' => $this->t('Last Name'),
                'class' => [
                    'form-input',
                    'w-full',
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
            '#required' => TRUE,
        ];

        $form['email'] = [
            '#type' => 'email',
            // '#title' => $this->t('Email'),
            '#attributes' => [
                'placeholder' => $this->t('Email'),
                'class' => [
                    'form-input',
                    'w-full',
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
            '#required' => TRUE,
        ];

        $form['mobile'] = [
            '#type' => 'tel',
            // '#title' => $this->t('Mobile Number'),
            '#attributes' => [
                'placeholder' => $this->t('Mobile Number'),
                'class' => [
                    'form-input',
                    'w-full',
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
            '#required' => TRUE,
        ];

        $form['gender'] = [
            '#type' => 'select',
            // '#title' => $this->t('Gender'),
            '#attributes' => [
                'class' => [
                    'form-select',
                    // 'w-auto',
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
            '#required' => TRUE,
        ];

        $form['resume'] = [
            '#type' => 'managed_file',
            '#title' => $this->t('Upload Resume'),
            '#required' => TRUE,
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
            '#type' => 'submit',
            '#value' => $this->t('Apply Now'),
        ];

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
}
