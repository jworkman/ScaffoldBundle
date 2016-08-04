# ScaffoldBundle

ScaffoldBundle gives you the ability to build quick CRUD functionality inside of your Symfony application. It also gives you the ability to serve JSON responses from each scaffold. Taking some of the best parts of Ruby on Rails and Symfony put together. With ScaffoldBundle you can generate easy flexible CRUD functionality around your Doctrine entities. 

## Requirements

php version >= 5.4
Symfony version >= 2.7

## Installation

With composer you can install the bundle by running the following in the root of your Symfony project:

    composer require jworkman/scaffold-bundle 

You must add the bundle to your `app/AppKernel.php`.

    $bundles = array(
        ...
        new JWorkman\ScaffoldBundle\JWorkmanScaffoldBundle(),
    );

Thats it! The bundle should be installed. Now you can move on to creating some scaffolds of your own. 


## Getting Started

In order to generate a scaffold you must have some Doctrine entities defined in your project. Lets say we have an entity called "UserProfile" defined in our `AcmeBundle` bundle. We must refer to that entity as `AcmeBundle:UserProfile`. We can run our scaffold generator command on that model to generate a scaffold controller. 

    app/console scaffold:generate

The first question is to specify an entity to build a scaffold for. In our case it will be `AcmeBundle:UserProfile`.

    The Entity shortcut name: AcmeBundle:UserProfile

The Second question is asking what friendly name you would like to use for your Scaffold. This should be a user readable title for this scaffold. It will be displayed across all CRUD views as a common name for the entity you are scaffolding. 

Note: this value should be a singular (not plural).

    Specify a friendly common title to use [User Profile]:

The third question will ask you what routing prefix you would like to use for this scaffold. In our example if we were editing a UserProfile at `/user_profile/4/edit` then `user_profile` is our prefix. It is the unique scope/namespace for our scaffold. 

    Specify a routing prefix to mount this scaffold [user_profile]:

The next question will ask you if you want to lock down any CRUD functionality for this scaffold. Read the documentation above the question for more information. You will have to list out all the methods you want locked down in a comma seperated format. 

    What HTTP do you want to disable (comma seperated)? none

    // Or if you want to disable the index, and edit actions

    What HTTP do you want to disable (comma seperated)? index,edit

The fifth question will ask you if you want to hide any specific fields from the index action. This DOES not hide the fields from the edit, or new actions. This is useful if you have something like passwords that you want to hide from the index action, but not the edit, or new forms. Below we will disable the field "password"

    Specify any field you would like to make private (Enter nothing to stop adding fields): password

The next few questions will go through each field on your entity and ask you what form field type to use for that field. Most of the time the generator will generate intelligent defaults based on your entity field types. This can be adjusted later. 

Once you are done defining your form field types it will ask you if you would like to enable the JSON api feature for this specific scaffold. Its a good practice to keep this disabled if you do not use it. In our case we will enable it. 

    Would you like to enable the JSON API for this scaffold? Yes

We need to define a target bundle. This defaults to the bundle that the entity is located in, but can be overridden to a different bundle. This is useful if you have a specifc bundle you want to place all of your scaffolds in instead of seperate bundles. 

    Specify a target bundle for the controller [AcmeBundle]:

Updating the routes is next. In order for the scaffold controller to map requests to it correctly it needs to append some routes to your target bundle's `routing.yml` file. It will ask if you want it to do it for you automatically. You will notice some new routes at the end of the file once this is done. 

    Would you like to automatically update your routes as well? Y

After the generator has completed you will notice it placed a new controller in your target bundle. In our case it was `src/AcmeBundle/Controller/UserProfileController.php`. If you open it up it will look something like this:

    <?php

    namespace AcmeBundle\Controller;

    use JWorkman\ScaffoldBundle\Controller\ScaffoldController;

    class UserProfileController extends ScaffoldController
    {

        /*
            entityName is the fully qualified entity name including the bundle
            namespace separated by a colon. An entity name must be specified
            in order to place a scaffold in context.
            Ex) To make the scaffold manage the "Event" entity

                protected $entityName = "AcmeBundle:Event";

        */
        protected $entityName       = "AcmeBundle:UserProfile";

        /*
            viewParameter is a property that you can specify twig to use for any
            entity results that are passed to the view.
            Ex) To use the twig name "events" in twig

                protected $viewParameter = "events";

        */
        protected $viewParameter    = "results";

        /*
            friendlyName is a property that you can specify the official title
            of this scaffold. This should be user friendly and formated to fit
            most method contexts.
            Ex) To make the scaffold refer to your "UserProfile" entity as "User Profiles"

                protected $friendlyName = "User Profile";

        */
        protected $friendlyName     = "User Profile";

        /*
            lockedMethods allows you to specify a list of forbidden methods on a
            scaffold. This defaults to all methods allowed.
            Ex) If you want to disabled the ability to delete an entity

                protected $lockedMethods = ['index','delete'];

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
            limit controls the maximum amount of results that can be loaded on one
            request. This is used in conjunction with pagination.
        */
        protected $limit            = 40;

        /*
            apiEnabled specifies if this entity should use JSON API support. To turn
            the API on just set this to true. The API will show all the columns that
            are specified by the columnMask property. This defaults to disabled.
            Ex) To enable the API of an entity

                protected $apiEnabled = true;

        */
        protected $apiEnabled       = false;

        /*
            routes specify the different route names for each action as they
            relate to your routing.yml file. This is important so all of the
            scaffold links are correct. It is formated by 'method' => 'route_name'
            Ex)
                protected $routes   = [ 'index' => 'event_index_route_name' ]
        */
        protected $routes           = [
            'index' => 'user_profile_index',
            'new'   => 'user_profile_new',
            'edit'  => 'user_profile_edit',
            'create' => 'user_profile_new',
            'delete' => 'user_profile_delete',
            'update' => 'user_profile_edit'
        ];

        /*
            This method should return a symfony form for the $entity param. An
            example is:

            return $this->createFormBuilder( $entity )
                ->add('title', 'text')
        */
        public function getForm( $entity )
        {
            return $this->createFormBuilder( $entity )
                    ->add("username", "text")
                    ->add("password", "hidden")
                    ->add("save", "submit")
            ;
        }

    }



You notice that we can change the form, and any of it's field types. There are also some other various properties that can be overridden at anytime to further customize the scaffold. There are few properties that you can add here as well. Take for example if we wanted to override the default scaffold twig tempaltes. We can do this by defining a property in our controller called `templates` that points to a bundle view resource directory. 

The below example shows how to override the CRUD templates, and use another bundle's templates instead. Add the following property in your scaffold controller:

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
    protected $templates   = "AcmeBundle:UserProfile";


The above example will use the views inside of `@AcmeBundle/Resources/views/UserProfile`. 

## Layout Template

In order for scaffolds to work you must define a base layout file for it to use. The default base layout file should be placed in `app/Resources/views/scaffold.html.twig`. You can define your own layout in that file or have one generated for you using the following command:

    app/console scaffold:layout

The generator will create a new layout file for scaffolds. Now you must add whatever styles you need to it. 


