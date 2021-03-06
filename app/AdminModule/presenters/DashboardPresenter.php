<?php

namespace App\AdminModule\Presenters;

use Nette,
	App\Model,
    Nette\Application\UI\Form,
    App\Components\BootstrapizeForm;


  class DashboardPresenter extends BasePresenter
{


    private $configModel;
    private $registrationModel;

    public function __construct( Model\Config $configModel, Model\Registration $registrationModel ) {
        $this->configModel = $configModel;
        $this->registrationModel = $registrationModel;
    }

    public function createComponentSwitchesForm() {
        $form = new Form();

        $form->addCheckbox( 'isRegistrationOpen', 'Registrace otevřena');
        $form->addText( 'registrationCapatity', 'Kapacita návštěvníků', 3)
            ->setType('number')
            ->addRule(Form::INTEGER, 'Must be number')
            ->addRule(Form::RANGE, 'Number must be between %d and %d', array(0,9999))
            ->setRequired('Must be valid number.');
        $form->addCheckbox( 'isVotingOpen', 'Hlasování přednášek - otevřeno');
        $form->addCheckbox( 'isVoteShows', 'Hlasování přednášek - zobrazeny počty')
                ->addConditionOn($form['isVotingOpen'], Form::EQUAL, TRUE)
                ->setRequired('Voting must be shown when is opened');
        $form->addText( 'talksCapatity', 'Kapacita přednáček', 3)
            ->setType('number')
            ->addRule(Form::INTEGER, 'Must be number')
            ->addRule(Form::RANGE, 'Number must be between %d and %d', array(1,9999))
            ->setRequired('Must be valid number.');
        $form->addCheckbox( 'isTalkLocationShows', 'Zobrazit umístění přednášky');
        $form->addSubmit( 'send', 'Uložit');

        $form->setDefaults( array(
            'isRegistrationOpen' => $this->configModel->getConfig( 'isRegistrationOpen', FALSE ),
            'registrationCapatity' => $this->configModel->getConfig( 'registrationCapatity', 0 ),
            'isVotingOpen' => $this->configModel->getConfig( 'isVotingOpen', FALSE ),
            'isVoteShows' => $this->configModel->getConfig( 'isVoteShows', FALSE ),
            'talksCapatity' => $this->configModel->getConfig( 'talksCapatity', 20 ),
            'isTalkLocationShows' => $this->configModel->getConfig( 'isTalkLocationShows', FALSE ),
        ) );

        $form->onSuccess[] = array( $this, 'processSwitches');
        BootstrapizeForm::bootstrapize( $form );
        return $form;
    }

    public function processSwitches( Form $form ) {
        $values = (array) $form->getValues();

        $this->setConfig( 'isRegistrationOpen', $values['isRegistrationOpen'] );
        $this->setConfig( 'registrationCapatity', $values['registrationCapatity'] );
        $this->setConfig( 'isVotingOpen', $values['isVotingOpen'] );
        $this->setConfig( 'isVoteShows', $values['isVoteShows'] );
        $this->setConfig( 'talksCapatity', $values['talksCapatity'] );
        $this->setConfig( 'isTalkLocationShows', $values['isTalkLocationShows'] );

        $this->flashMessage('OK, sucessfull saved.', 'success');
        $this->redirect( 'this' );

    }

    private function setConfig( $id, $value ) {
        $currentValue = $this->configModel->getConfig( $id );
        if( $currentValue !== $value) {
            $this->configModel->setConfig( $id, $value );
        }
    }
}
