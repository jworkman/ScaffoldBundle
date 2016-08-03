<?php

namespace JWorkman\ScaffoldBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Symfony\Component\Console\Question\ChoiceQuestion;

class GenerateCommand extends GenerateDoctrineCommand
{

    protected $view = [];

    protected function configure()
    {
        $this
            ->setName('scaffold:generate')
            ->addOption('entity', null, InputOption::VALUE_REQUIRED, 'The entity class name to initialize (shortcut notation)')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'The common name to use for the scaffold.')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'The URL mount prefix point for the scaffold.')
            ->addOption('api', false, InputOption::VALUE_REQUIRED, 'Allows the scaffold to serve a JSON api.')
            ->addOption('target', false, InputOption::VALUE_REQUIRED, 'Override the default bundle target.')
            ->setDescription('Generate a scaffold controller.');
    }

    protected function createGenerator()
    {
        return;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->em                                   = $this->getContainer()->get('doctrine')->getManager();
        $this->questionHelper                       = $this->getQuestionHelper();

        // Load the entity name
        $this->view['entity']                       = $this->getEntity( $input, $output );

        // Load the friendly title
        $this->view['title']                        = $this->getFriendlyTitle( $input, $output );

        // Load the friendly title
        $this->view['routing']                      = [];
        $this->view['routing']['prefix']            = $this->getRoutingPrefix( $input, $output );
        $this->view['routing']['locked_methods']    = $this->getLockedMethods( $input, $output );

        // Load the column mask
        $this->view['column_mask']                  = $this->getColumnMask( $input, $output );

        // Load the API config
        $this->view['api']                          = $this->getAPI( $input, $output );

        // Load the target bundle
        $this->view['target']                       = $this->getTargetBundle( $input, $output );

        $this->generateScaffold( $input, $output );

    }

    protected function getAPI(InputInterface $input, OutputInterface $output)
    {

        $question = new ConfirmationQuestion('<question>Would you like to enable the JSON API for this scaffold?</question>', $input->getOption('api'));
        return $this->questionHelper->ask($input, $output, $question);

    }

    protected function getTargetBundle(InputInterface $input, OutputInterface $output)
    {
        $target = [];

        if ( !$input->getOption('target') ) {
            $target = $this->view['entity']['bundle_name'];
        } else {
            $target = $input->getOption('target');
        }

        // Get a list of all the available bundles for autotab complete
        $bundleNames = array_keys($this->getContainer()->get('kernel')->getBundles());

        // Build the question with all entities passed in for autocomplete
        $question = new Question($this->questionHelper->getQuestion('<question>Specify a target bundle for the controller</question>', $target), $target);
        $question->setAutocompleterValues($bundleNames);

        // Ask for the entity
        $target_bundle = $this->questionHelper->ask($input, $output, $question);

        return [
            'bundle_name' => $target_bundle,
            'namespace'   => $this->getContainer()->get('kernel')->getBundle( $target_bundle )->getNamespace()
        ];

    }

    protected function generateScaffold(InputInterface $input, OutputInterface $output)
    {
        $this->view['class'] = ucfirst( $this->view['entity']['entity_name'] ) . 'Controller';
        $kernel = $this->getContainer()->get('kernel');
        $target_path = $kernel->locateResource('@' . $this->view['target']['bundle_name']);
        $target_path = rtrim($target_path, '/') . '/Controller/' . $this->view['class'] . '.php';

        // Get the twig env so we can run it on the template file
        $twig = new \Twig_Environment(new \Twig_Loader_String());
        $rendered = $twig->render(
          file_get_contents( $kernel->locateResource('@JWorkmanScaffoldBundle/Generate/Controller.twig') ),
          $this->view
        );

        if ( file_exists( $target_path ) ) {
            $question   = new ConfirmationQuestion('<error>A controller with the same name already exists! Would you like to overwrite it?</error>', false);
            $overwrite  = $this->questionHelper->ask($input, $output, $question);
            if ( !$overwrite ) { return false; }
        }

        return file_put_contents( $target_path, $rendered );

    }

    protected function getEntity(InputInterface $input, OutputInterface $output)
    {

        $entity_name            = new Question( '<question>Enter an entity name: </question>', false );

        // Get a list of all the available bundles for autotab complete
        $bundleNames = array_keys($this->getContainer()->get('kernel')->getBundles());

        // Build the question with all entities passed in for autocomplete
        $question = new Question($this->questionHelper->getQuestion('<question>The Entity shortcut name</question>', $input->getOption('entity')), $input->getOption('entity'));
        $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));
        $question->setAutocompleterValues($bundleNames);

        // Ask for the entity
        $entity   = $this->questionHelper->ask($input, $output, $question);


        // If the entity does not exist then notify user it is not found
        $entity_chunks  = explode( ':', trim($entity) );
        $bundle_name    = $entity_chunks[0];
        $entity_name    = $entity_chunks[1];

        // Now set the view parameters
        $view                     = [];
        $view['entity_name']      = $entity_name;
        $view['bundle_name']      = $bundle_name;
        $view['fq_entity_name']   = $entity;

        return $view;
    }


    protected function getFriendlyTitle(InputInterface $input, OutputInterface $output)
    {

        if ( !$input->getOption('title') ) {
            $title = $this->view['entity']['entity_name'];
            $title = preg_replace('/(?<=\\w)(?=[A-Z])/'," $1", $title);
            $title = ucwords($title);
        } else {
            $title = $input->getOption('title');
        }

        $question = new Question($this->questionHelper->getQuestion('<question>Specify a friendly common title to use</question>', $title), $title);
        return $this->questionHelper->ask($input, $output, $question);

    }

    protected function getRoutingPrefix(InputInterface $input, OutputInterface $output)
    {

        if ( !$input->getOption('prefix') ) {
            $prefix = $this->view['entity']['entity_name'];
            $prefix = preg_replace('/(?<=\\w)(?=[A-Z])/',"_$1", $prefix);
            $prefix = strtolower($prefix);
        } else {
            $prefix = $input->getOption('prefix');
        }

        $question = new Question($this->questionHelper->getQuestion('<question>Specify a routing prefix to mount this scaffold</question>', $prefix), $prefix);
        return $this->questionHelper->ask($input, $output, $question);

    }

    protected function getColumnMask(InputInterface $input, OutputInterface $output)
    {
        // First we need to get the columns of this entity
        $meta_data  = $this->em->getClassMetadata( $this->view['entity']['fq_entity_name'] )->getColumnNames();

        $question   = new Question('<question>Specify any field you would like to make private (Enter nothing to stop adding fields):</question>', false);

        $fields     = [];

        // Start the loop field
        while(true) {
            $field = $this->questionHelper->ask($input, $output, $question);

            if ( !$field ) {
                break;
            }

            array_push($fields, $field);
        }

        return $fields;
    }

    protected function getLockedMethods(InputInterface $input, OutputInterface $output)
    {

        $text = <<<EOT
HTTP methods in the scaffold bundle are used to determine what context the reqeust
is in. The URL `/users/1` can be used differently depending on the HTTP method specified
by the browser. For example GET `/users/1` is used to get the form to edit the user with
the ID of `1`, but `POST /users/1` is used to physically update the user with the ID of 1.

There are 5 different methods when using a RESTful scaffold controller. Listed below
is a table showing what each method does.

    +-------------------------------------------------------------------------------------------------+
    | Method |      Browser Request         |            Description                                  |
    +=================================================================================================+
    | index  | GET /{$this->view['routing']['prefix']}              | Lists all the users and actions
    +-------------------------------------------------------------------------------------------------+
    | edit   | GET /{$this->view['routing']['prefix']}/{id}         | Shows a form to edit user of {id}
    +-------------------------------------------------------------------------------------------------+
    | update | POST /{$this->view['routing']['prefix']}/{id}        | Updates a user with an id of {id}
    +-------------------------------------------------------------------------------------------------+
    | new    | GET /{$this->view['routing']['prefix']}/new          | Shows a form to create a new user
    +-------------------------------------------------------------------------------------------------+
    | create | POST /{$this->view['routing']['prefix']}/new         | Creates a new user
    +-------------------------------------------------------------------------------------------------+
    | delete | GET /{$this->view['routing']['prefix']}/{id}/delete  | Removes user with an id of {id}
    +-------------------------------------------------------------------------------------------------+

To follow best RESTful practices you should only enable the methods you absolutely
need for your application. Leaving all methods enabled can mean bigger security
risks, or accidental data loss by users.

List out all the methods you would like to lock down on your scaffold below.
EOT;


        $output->writeln($text);
        $question = new ChoiceQuestion(
            '<question>What HTTP do you want to disable (comma seperated)?</question>',
            array( 'none' => 'none', 'index' => 'index', 'edit' => 'edit', 'update' => 'update', 'new' => 'new', 'create' => 'create', 'delete' => 'delete'),
            'none'
        );
        $question->setMultiselect(true);
        $locked_methods = $this->questionHelper->ask($input, $output, $question);
        if ( in_array('none', $locked_methods) ) { $locked_methods = []; }
        return array_map('trim', $locked_methods);

    }


}
