<?php

namespace JWorkman\ScaffoldBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Form\FormRendererInterface;

class ScaffoldController extends Controller
{

    /*
        indexFilters are any extra SQL filters that need to be included on the
        index action.
        Ex) To add a filter that includes only records with an active state

            protected $indexFilters = [ 'active' => true ];

    */
    protected $indexFilters     = [];

    /*
        editFilters are any extra SQL filters that need to be included on the
        edit action.
        Ex) To add a filter that includes only records with a unlocked state

            protected $editFilters = [ 'locked' => false ];

    */
    protected $editFilters     = [];

    /*
        deleteFilters are any extra SQL filters that need to be included on the
        delete action.
        Ex) To add a filter that includes only records with an trashed state

            protected $deleteFilters = [ 'trashed' => false ];

    */
    protected $deleteFilters     = [];

    /*
        entityName is the fully qualified entity name including the bundle
        namespace separated by a colon. An entity name must be specified
        in order to place a scaffold in context.
        Ex) To make the scaffold manage the "Event" entity

            protected $entityName = "Acme:Event";

    */
    protected $entityName       = "";

    /*
        viewParameter is a property that you can specify twig to use for any
        entity results that are passed to the view. This defaults to "results"
        but can be overridden.
        Ex) To use the twig name "events" in twig

            protected $viewParameter = "events";

        Ex) To access it in twig

            {% for event in events %}

    */
    protected $viewParameter    = "results";

    /*
        limit controls the maximum amount of results that can be loaded on one
        request. This is used in conjunction with pagination.
    */
    protected $limit            = 40;

    /*
        indexOrder specifies how the results will order. Defaults to ordering by
        the primary key property
        Ex) To make the scaffold order results by the "updated" in a descending order

            protected $indexOrder = ['updated' => 'DESC'];

    */
    protected $indexOrder       = null;

    /*
        primaryKey sets the primary identifier on this entity. This should always be
        "id" but in some cases this could be some other column.
    */
    protected $primaryKey       = "id";

    /*
        form specifies the fully qualified class name for the form that will represent
        this entity. It must include a full namespace with escaped backslashes.
        Ex)

            protected $form = "\\AcmeBundle\\Form\\UserProfileType";

    */
    protected $form             = "";

    /*
        lockedActions allows you to specify a list of forbidden methods on a
        scaffold. This defaults to all methods allowed.
        Ex) If you want to disabled the ability to delete an entity

            protected $lockedMethods = ['delete'];

    */
    protected $lockedMethods    = [];

    /*
        columnMask allows you to specify a list of entity columns to pass to the
        view. If you are dealing with passwords this should be setup to hide the
        password.
        Ex) To disable the "password" column of an entity

            protected $columnMask = [ 'password' => false ];

    */
    protected $columnMask       = [];

    /*
        apiEnabled specifies if this entity should use JSON API support. To turn
        the API on just set this to true. The API will show all the columns that
        are specified by the columnMask property. This defaults to disabled.
        Ex) To enable the API of an entity

            protected $apiEnabled = true;

    */
    protected $apiEnabled       = false;

    /*
        datetimeFormat specifies the default date/time stamp for date value
        objects coming back from the database to the view.
    */
    protected $datetimeFormat   = 'd/m/Y \@ g:ia';


    /*
        routes specify the different route names for each action as they
        relate to your routing.yml file. This is important so all of the
        scaffold links are correct. It is formated by 'method' => 'route_name'
        Ex)
            protected $routes   = [ 'index' => 'event_index_route_name' ]
    */
    protected $routes   = [];


    /*
        templates can override the default views for a scaffold. If you have your
        own view templates you would like to use then use this property to point
        the scaffold towards your custom templates. It must be pointed at the
        directory that contains the following directory structure:
        Ex)
            protected $templates = "AcmeBundle:Events";

            would point to the following directory structure:

            AcmeBundle
            - Resources
            -- views
            --- Events
            ---- index.html.twig
            ---- edit.html.twig
            ---- new.html.twig
    */
    protected $templates   = "JWorkmanScaffoldBundle:Default";



    private function beforeHook()
    {

        $this->initGlobals();

        // Parse the action
        $action = $this->getRequest()->attributes->get('_controller');
        $action = explode('::', $action);
        $action = rtrim(array_pop($action), 'Action');
        $this->method = $action;
        $this->action = $action;

        // We have to modify $action to be update, or create if we are in POST
        if ( $this->action == "new" && $this->getRequest()->isMethod('POST') ) {
            $this->method = "create";
        } elseif ( $this->action == "edit" && $this->getRequest()->isMethod('POST') ) {
            $this->method = "update";
        }

        // First we will check if this method is allowed
        if (in_array($this->method, $this->lockedMethods))
        { throw $this->createAccessDeniedException("Access is denied."); }

        // Determine if this was a request for the API
        $this->apiRequested = ($this->request->isXmlHttpRequest()) ? true : false;

        if ( method_exists($this, 'before') ) {
            return $this->before();
        }

    }

    private function afterHook()
    {
        if ( method_exists($this, 'after') ) { return $this->after(); }
    }

    private function beforeSaveHook()
    {
        if ( method_exists($this, 'beforeSave') ) { return $this->beforeSave(); }
    }

    private function afterSaveHook()
    {
        if ( method_exists($this, 'afterSave') ) { return $this->afterSave(); }
    }

    private function beforeDeleteHook()
    {
        if ( method_exists($this, 'beforeDelete') ) { return $this->beforeDelete(); }
    }

    private function beforeDeleteHook()
    {
        if ( method_exists($this, 'beforeDelete') ) { return $this->beforeDelete(); }
    }

    private function camelToUnderscore( $str )
    {
        return ltrim(strtolower(preg_replace('/[A-Z]/', '_$0', $str)), '_');
    }

    private function dashesToCamelCase($string, $capitalizeFirstCharacter = false)
    {

        $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));

        if (!$capitalizeFirstCharacter) {
            $str[0] = strtolower($str[0]);
        }

        return $str;
    }

    private function humanize($str, $ucfirst = false) {
        $str = $this->camelToUnderscore($str);
        $str = str_replace('_', ' ', $str);
        if ( $ucfirst ) {
            return ucfirst($str);
        } else {
            return ucwords($str);
        }
    }

    private function getFormatedValue( $value )
    {

        if ( $value instanceof \DateTime || $value instanceof \Date ) {
            return $value->format( $this->datetimeFormat );
        }

        if ( is_bool($value) ) { return ($value) ? "Yes" : "No"; }

        return $value;

    }


    private function maskColumns( $data )
    {

        $collection     = [];
        $cols           = $this->em->getClassMetadata( $this->entityName )->getColumnNames();
        if ( empty($this->columnMask) ) {
            $this->columnMask = $cols;
        }

        foreach($data as $index => $row)
        {
            $record = [];
            foreach($this->columnMask as $key => $label)
            {
                $l = (is_numeric($key)) ? $this->humanize($label) : $key;
                $property = (is_numeric($key)) ? $label : $key;
                $getter = "get" . $this->dashesToCamelCase( $property, true );
                $v = $row->$getter();
                $value = $this->getFormatedValue( $v );
                $record[$property] = [ 'label' => $l, 'value' => $value, 'is_pk' => (($key == $this->primaryKey) ? true : false) ];
            }

            array_push($collection, $record);

        }

        return $collection;

    }


    private function getJSONResponse( $masked_data = [] )
    {

        $records = [];

        foreach($masked_data as $row) {

            $record = [];

            foreach($row as $column_name => $meta_data) {
                $record[$column_name] = $meta_data['value'];
            }

            array_push($records, $record);

        }

        return new JsonResponse($records);

    }



    /*
        The index action should show a list of all records located at /{entity}
    */
    public function indexAction()
    {
        // Execute the before action filter
        $this->beforeHook();

        // Figure out what page was requested, and format it for our SQL call
        if ( isset($_GET['page']) && $_GET['page'] && is_numeric($_GET['page']) && (int)$_GET['page'] > 0 ) {
            $page = ((int)$_GET['page']) - 1;
        } else {
            $page = 0;
        }

        // Grab the entity manager, repo, and our results from the database
        // Initialize all class globals we will need
        $this->beforeHook();
        $this->results    = $this->repo->findBy(
            $this->indexFilters,
            $this->indexOrder,
            $this->limit,
            ($page * $this->limit)
        );

        // Get the count for pagination
        // We must first get the table name of the entity
        $table = $this->em->getClassMetadata( $this->entityName )->table['name'];

        // Grab a total count of all the rows in the entity table for paignation
        $count      = $this->repo->createQueryBuilder('f')
                            ->select('COUNT(f.id)')
                            ->getQuery()
                            ->getSingleScalarResult();


        // Build a pagination object to pass to the view
        $pagination = $this->buildPagination($page, $this->limit, $count, $this->results);

        // Format the data columns
        $masked_data = $this->maskColumns($this->results);

        // Call the after hook
        $this->afterHook();

        
        // If this was an API request then render JSON
        if ( ($this->apiRequested || isset($_GET['json'])) && $this->apiEnabled ) {
            return $this->getJSONResponse( $masked_data );
        }

        // Finally render the view
        return $this->render(
            $this->templates . ':index.html.twig',
            $this->getTwigParams([ $this->viewParameter => $masked_data, 'pagination' => $pagination ], 'index')
        );

    }

    /*
        The edit action should show a form for updating an entity loaded in by primaryKey located at /{entity}/{pk}/edit
    */
    public function editAction( $pk )
    {

        // Add the primary key filter to the filters array before we pass it to our query
        $this->editFilters[ $this->primaryKey ] = $pk;

        // Grab the entity manager, repo, and our results from the database
        // Initialize all class globals we will need
        $this->beforeHook();
        $this->entity    = $this->repo->findOneBy(
            $this->editFilters
        );

        // If the entity was not found then throw a new 404
        if ( !$this->entity ) {
            throw $this->createNotFoundException($this->friendlyName . ' not found!');
        }

        // Initialize the form
        $form = $this->getForm( $this->entity )->getForm();

        // Bind the request to the form
        $form->handleRequest( $this->request );

        // If the form was submitted and is valid then lets save it to the db
        if ( $form->isSubmitted() && $form->isValid() ) {

            $this->em->persist($this->entity);

            // Call the before save hook
            $this->beforeSaveHook();

            $this->em->flush();

            // Call the after hook
            $this->afterSaveHook();

            // Add our flash messages
            $this->session->getFlashBag()->add('success', $this->friendlyName . ' has been updated!');

            // Go back to the index page for this entity
            return $this->redirectToRoute( $this->routes['index'] );

        }

        // Call the after hook
        $this->afterHook();

        // Render an edit form view
        return $this->render(
            $this->templates . ':edit.html.twig',
            $this->getTwigParams([ 'form' => $form->createView(), 'pk' => $pk ])
        );

    }

    /*
        This function initializes all of the global class properties that are
        used in almost all of our actions. It acts as a second constructor when
        symfony gets done initializing the controller.
    */
    private function initGlobals()
    {
        if (is_null($this->indexOrder)) {
            $this->indexOrder = [];
            $this->indexOrder[$this->primaryKey] = 'ASC';
        }
        $this->em   = $this->getDoctrine()->getManager();
        $this->repo = $this->em->getRepository( $this->entityName );
        $this->request = $this->getRequest();
        $this->session = $this->request->getSession();
    }

    /*
        This function creates an array to pass to the twig environment that includes
        class globals that may be needed in the view as well as contextual parameters.
        It merges the global variables with the $params argument.
    */
    private function getTwigParams( $params = [] )
    {

        return array_merge(
            $params,
            [
                "friendlyName"  => $this->friendlyName,
                "viewParameter" => $this->viewParameter,
                "action"        => $this->action,
                "method"        => $this->method,
                "primaryKey"    => $this->primaryKey,
                "routes"        => $this->routes
            ]
        );

    }

    // Creates a form for a new entity record
    public function newAction()
    {

        // Initialize all class globals we will need
        $this->beforeHook();

        // Grab the class name of the entity we are creating so we can make an instance
        $metadata = $this->em->getClassMetadata( $this->entityName );
        $entityClassName = $metadata->rootEntityName;
        $this->entity = new $entityClassName();

        // Initialize the form
        $form = $this->getForm( $this->entity )->getForm();

        // Bind the request to the form
        $form->handleRequest( $this->request );

        // If the form was submitted and is valid then lets save it to the db
        if ( $form->isSubmitted() && $form->isValid() ) {

            $this->em->persist($this->entity);
            // Call the before save hook
            $this->beforeSaveHook();

            $this->em->flush();

            // Call the after hook
            $this->afterSaveHook();

            // Add our flash messages
            $this->session->getFlashBag()->add('success', 'A new ' . $this->friendlyName . ' has been created!');

            // Call the after hook
            $this->afterHook();

            // Go back to the index page for this entity
            return $this->redirectToRoute( $this->routes['index'] );

        }

        // Call the after hook
        $this->afterHook();

        // Render the form view
        return $this->render(
            $this->templates . ':new.html.twig',
            $this->getTwigParams([ 'form' => $form->createView() ], 'new')
        );

    }

    public function deleteAction( $pk )
    {

        $this->beforeHook();

        // We need to add the primary key filter to our default filters
        $this->deleteFilter[ $this->primaryKey ] = $pk;

        // First we must find the entity we are trying to delete
        $this->entity = $this->repo->findOneBy( $this->deleteFilter );

        // If the entity was not found then throw a new 404
        if ( !$this->entity ) {
            throw $this->createNotFoundException($this->friendlyName . ' not found!');
        }

        // Lets actually remove it from the DB
        $this->em->remove( $this->entity );

        // Call the before save hook
        $this->beforeDeleteHook();

        // Make the SQL calls
        $this->em->flush();

        // Call the after hook
        $this->afterDeleteHook();

        // Add our flash messages
        $this->session->getFlashBag()->add('error', $this->friendlyName . ' has been deleted!');

        // Call the after hook
        $this->afterHook();

        // Go back to the index page for this entity
        return $this->redirectToRoute( $this->routes['index'] );


    }


    /*
        This function creates a form instance from the Forms namespace with an
        entity context.
    */
    // public function getForm( $entity )
    // {
    //     $fqFormClass = $this->form;
    //     return $this->createForm( new $fqFormClass(), $entity );
    // }

    /*
        This function creates a pagination array to pass to the view so that
        twig can display the properties.
    */
    private function buildPagination( $page, $limit, $total, $resultCount )
    {


        $remainder_results = ($total % $this->limit);
        $total_pages = ceil($total / $this->limit);
        $friendlyPage = $page + 1;
        $from = $page * $this->limit;
        $to   = $from + count($resultCount);

        $pagination = [
            'page' => $friendlyPage,
            'total_pages' => $total_pages,
            'next_page' => ($page + 2),
            'previous_page' => ($page),
            'has_previous' => (($page - 1 < 0) ? false : true),
            'has_next' => (($page + 1 > $total_pages) ? false : true),
            'page_range' => range( max( 1, $friendlyPage - 5 ), max( $friendlyPage, $total_pages ) ),
            'listings' => [
                'from'  => ($from + 1),
                'to'    => $to,
                'total' => $total
            ]
        ];

        return $pagination;

    }

}
