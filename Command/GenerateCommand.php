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
use Symfony\Component\Yaml\Yaml;

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

        $mcApiKey   = "bab961bf7098155a95123837453a0ddd-us7";
        $qcrewList  = "46319a25ec";

        try {
            $mc = new \Mailchimp( $mcApiKey ); 
            var_dump($mc->lists->getList(['list_id' => $qcrewList]));
        } catch(\Exception $e) {
            echo $e->getMessage();
        }
        exit;

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

        // Load the column mask
        $this->view['form']                         = $this->getForm( $input, $output );

        // Load the API config
        $this->view['api']                          = $this->getAPI( $input, $output );

        // Load the target bundle
        $this->view['target']                       = $this->getTargetBundle( $input, $output );

        $question       = new ConfirmationQuestion('<question>Would you like to automatically update your routes as well?</question>', $input->getOption('api'));
        $updateRoutes   = $this->questionHelper->ask($input, $output, $question);

        if ( $updateRoutes ) {
            $this->updateRoutes( $input, $output );
        }

        if ( $this->generateScaffold( $input, $output ) ) {
            $output->writeln('<info>Scaffold was generated successfully!</info>');
        }

        

    }

    private function fieldTypeToFormType( $type )
    {
        if ( $type == "string" ) { return "text"; }
        if ( $type == "boolean" ) { return "checkbox"; }
        if ( $type == "decimal" ) { return "number"; }
        return $type;
    }

    protected function getForm(InputInterface $input, OutputInterface $output)
    {

        $fields = [];

        // First we need to get the columns of this entity
        $metadata   = $this->em->getClassMetadata( $this->view['entity']['fq_entity_name'] );

        foreach($metadata->fieldMappings as $fieldName => $field) {
            // If this field is a primary key then ignore
            if ( isset($field['id']) && $field['id'] ) { continue; }

            // Map the field type to its form field type
            $type = $this->fieldTypeToFormType( $field['type'] );

            $question = new Question($this->questionHelper->getQuestion('<question>What type of form field for "' . $field['fieldName'] . '"?</question>', $type), $type);
            $question->setAutocompleterValues( ['text', 'textarea', 'email', 'integer', 'money', 'number', 'password', 
                'percent', 'search', 'url', 'range', 'choice', 'entity', 'country', 'language', 'locale', 'timezone', 
                'currency', 'date', 'datetime', 'time', 'birthday', 'checkbox', 'file', 'radio', 'collection', 'repeated', 
                'hidden', 'button', 'reset', 'submit'] );


            // Ask for the entity
            $type = $this->questionHelper->ask($input, $output, $question);

            array_push($fields, ['name' => $field['fieldName'], 'type' => $type]);

        }

        // add a submit button to the fields list 
        array_push($fields, ['name' => 'save', 'type' => 'submit']);

        return [ 'fields' => $fields ];

    }

    protected function updateRoutes(InputInterface $input, OutputInterface $output)
    {

        $kernel         = $this->getContainer()->get('kernel');
        $target_path    = $kernel->locateResource('@' . $this->view['target']['bundle_name']);
        $target_path    = rtrim($target_path, '/');

        $this->getContainer()->get('filesystem')->mkdir($target_path.'/Resources/config/');
        $routes_path    = $target_path . '/Resources/config/routing.yml';
        
        $yaml           = file_get_contents($routes_path);
        // First we must check for existing routes
        $routes         = Yaml::parse($yaml);

        foreach( ['index','new','edit','update','create','delete'] as $method )
        {
            $route_name = $this->view['routing']['prefix'] . '_' . $method;
            if ( isset($routes[$route_name]) ) { 
                $output->writeln('<error>Routing conflict! Route "' . $route_name . '" was already defined in ' . $routes_path .  '. Aborting!</error>'); 
                return false; 
            }
        }

        // Now that we checked our routes we can add them 
        $new_routes     = [];

        // Index route
        $new_routes[ $this->view['routing']['prefix'] . '_index' ] = [
            'path' => '/' . $this->view['routing']['prefix'],
            'defaults' => [
                '_controller' => $this->view['target']['bundle_name'] . ':' . $this->rightTrim($this->view['class'], 'Controller', true) . ':index'
            ],
            'methods' => ['GET']
        ];

        // New route
        $new_routes[ $this->view['routing']['prefix'] . '_new' ] = [
            'path' => '/' . $this->view['routing']['prefix'] . '/new',
            'defaults' => [
                '_controller' => $this->view['target']['bundle_name'] . ':' . $this->rightTrim($this->view['class'], 'Controller', true) . ':new'
            ],
            'methods' => ['GET']
        ];

        // Create route
        $new_routes[ $this->view['routing']['prefix'] . '_create' ] = [
            'path' => '/' . $this->view['routing']['prefix'] . '/new',
            'defaults' => [
                '_controller' => $this->view['target']['bundle_name'] . ':' . $this->rightTrim($this->view['class'], 'Controller', true) . ':new'
            ],
            'methods' => ['POST']
        ];

        // Edit route
        $new_routes[ $this->view['routing']['prefix'] . '_edit' ] = [
            'path' => '/' . $this->view['routing']['prefix'] . '/{pk}',
            'defaults' => [
                '_controller' => $this->view['target']['bundle_name'] . ':' . $this->rightTrim($this->view['class'], 'Controller', true) . ':edit'
            ],
            'methods' => ['GET']
        ];

        // Update route
        $new_routes[ $this->view['routing']['prefix'] . '_update' ] = [
            'path' => '/' . $this->view['routing']['prefix'] . '/{pk}',
            'defaults' => [
                '_controller' => $this->view['target']['bundle_name'] . ':' . $this->rightTrim($this->view['class'], 'Controller', true) . ':edit'
            ],
            'methods' => ['POST']
        ];

        // Delete route
        $new_routes[ $this->view['routing']['prefix'] . '_delete' ] = [
            'path' => '/' . $this->view['routing']['prefix'] . '/{pk}/delete',
            'defaults' => [
                '_controller' => $this->view['target']['bundle_name'] . ':' . $this->rightTrim($this->view['class'], 'Controller', true) . ':delete'
            ],
            'methods' => ['GET']
        ];

        $yaml .= PHP_EOL . Yaml::dump($new_routes);

        return file_put_contents($routes_path, $yaml);

    }

    private function rightTrim($str, $needle, $caseSensitive = true)
    {
        $strPosFunction = $caseSensitive ? "strpos" : "stripos";
        if ($strPosFunction($str, $needle, strlen($str) - strlen($needle)) !== false) {
            $str = substr($str, 0, -strlen($needle));
        }
        return $str;
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
        $this->view['class'] = ucfirst( $view['entity_name'] ) . 'Controller';

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
            '<question>What HTTP do you want to disable (comma separated)?</question>',
            array( 'none' => 'none', 'index' => 'index', 'edit' => 'edit', 'update' => 'update', 'new' => 'new', 'create' => 'create', 'delete' => 'delete'),
            'none'
        );
        $question->setMultiselect(true);
        $locked_methods = $this->questionHelper->ask($input, $output, $question);
        if ( in_array('none', $locked_methods) ) { $locked_methods = []; }
        return array_map('trim', $locked_methods);

    }


}
