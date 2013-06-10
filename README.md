# DatatablesBundle

Symfony2 Datatables For Doctrine Entities.

Before installing DatatablesBundle you need to have a working installation of Symfony2.

# Screenshot

<div style="text-align:center"><img alt="Screenshot" src="https://github.com/stwe/DatatablesBundle/raw/master/Resources/screenshots/screenshot1.jpg"></div>

# Installation

1. Download DatatablesBundle using composer

    Add DatatablesBundle in your composer.json:

    ```js
    {
        "require": {
            "sg/datatablesbundle": "dev-master"
        }
    }
    ```

    Tell composer to download the bundle by running the command:

    ``` bash
    $ php composer.phar update
    ```

2. Enable the bundle

    Enable the bundle in the kernel:

    ``` php
    <?php
    // app/AppKernel.php

    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Sg\DatatablesBundle\SgDatatablesBundle()
        );
    }
    ```

3. Your base.html.twig

    ``` html
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="UTF-8" />
            <title>{% block title %}Welcome!{% endblock %}</title>
            {% block javascripts %}{% endblock %}
            {% block stylesheets %}{% endblock %}
            <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />
        </head>
        <body>
            {% block body %}{% endblock %}
        </body>
    </html>
    ```

4. Bootstrap 2.3 installation

The DatatablesBundle no contains the assets files from Twitter Bootstrap. You can e.g. download a ZIP archive with the files
from the Bootstrap repository on Github.

* Copy into the DatatablesBundle\Resources\public\css directory the bootstrap css file.
* Copy into the DatatablesBundle\Resources\public\img directory the bootstrap icons files.
* Copy into the DatatablesBundle\Resources\public\js directory the bootstrap js file.

# Example

1. Create your layout.html.twig

    ``` html
    {% extends '::base.html.twig' %}

    {% block title %}UserBundle{% endblock %}

    {% block javascripts %}
        <script src="{{ asset('bundles/sgdatatables/js/jquery-2.0.2.min.js') }}" type="text/javascript"></script>
        <script src="{{ asset('bundles/sgdatatables/js/bootstrap.min.js') }}" type="text/javascript"></script>
        <script src="{{ asset('bundles/sgdatatables/js/jquery.dataTables.min.js') }}" type="text/javascript"></script>
        <script src="{{ asset('bundles/sgdatatables/js/dataTables_bootstrap.js') }}" type="text/javascript"></script>
    {% endblock %}

    {% block stylesheets %}
        <link href="{{ asset('bundles/sgdatatables/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
        <link href="{{ asset('bundles/sgdatatables/css/dataTables_bootstrap.css') }}" rel="stylesheet" type="text/css" />
    {% endblock %}

    {% block body%}
        <div class="container">
            {% block content %}
            {% endblock %}
        </div>
    {% endblock %}
    ```

2. Create your Datatables class

    ``` php
    <?php

    namespace Sg\UserBundle\Datatables;

    use Sg\DatatablesBundle\Datatable\AbstractDatatableView;
    use Sg\DatatablesBundle\Datatable\Field;

    /**
     * Class UserDatatable
     *
     * @package Sg\UserBundle\Datatables
     */
    class UserDatatable extends AbstractDatatableView
    {
        /**
         * {@inheritdoc}
         */
        public function build()
        {
            $this->setTableId('user_datatable');
            $this->setSAjaxSource('user_results');

            $this->setTableHeaders(array(
                'Username',
                'Email',
                'Enabled',
                ''
            ));

            $nameField = new Field('username');

            $emailField = new Field('email');

            $enabledField = new Field('enabled');
            $enabledField->setBSearchable('false');
            $enabledField->setSWidth('90');
            $enabledField->setMRender("render_boolean_icons(data, type, full)");

            $idField = new Field('id');
            $idField->setBSearchable('false');
            $idField->setBSortable('false');
            $idField->setMRender("render_action_icons(data, type, full)");
            $idField->setSWidth('92');

            $this->addField($nameField);
            $this->addField($emailField);
            $this->addField($enabledField);
            $this->addField($idField);

            $this->setShowPath('user_show');
            $this->setEditPath('user_edit');
            $this->setDeletePath('user_delete');
        }
    }
    ```

3. Create your index.html.twig

    ``` html
    {% extends 'SgUserBundle::layout.html.twig' %}

    {% block title %}{{ title }}{% endblock %}

    {% block content %}

        <h2>{{ title }}</h2>

        {{ datatable_render(datatable) }}

    {% endblock %}
    ```

4. Add controller actions

    ``` php
    /**
     * Lists all User entities.
     *
     * @Route("/", name="user")
     * @Method("GET")
     * @Template()
     *
     * @return array
     */
    public function indexAction()
    {
        /**
         * @var \Sg\DatatablesBundle\Factory\DatatableFactory $factory
         */
        $factory = $this->get('sg_datatables.factory');

        /**
         * @var \Sg\DatatablesBundle\Datatable\AbstractDatatableView $datatableView
         */
        $datatableView = $factory->getDatatableView('Sg\UserBundle\Datatables\UserDatatable');

        return array(
            'title' => 'Enabled Users',
            'datatable' => $datatableView,
        );
    }

    /**
     * Get all User entities.
     *
     * @Route("/results", name="user_results")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexResultsAction()
    {
        /**
         * @var \Sg\DatatablesBundle\Datatable\DatatableData $datatable
         */
        $datatable = $this->get('sg_datatables')->getDatatable('SgUserBundle:User');

        /**
         * @var \Doctrine\ORM\QueryBuilder $qb
         */
        $callbackFunction =

            function($qb)
            {
                $andExpr = $qb->expr()->andX();
                $andExpr->add($qb->expr()->eq('fos_user.enabled', '1'));
                $qb->andWhere($andExpr);
            };

        $datatable->addWhereBuilderCallback($callbackFunction);

        return $datatable->getSearchResults();
    }
    ```


