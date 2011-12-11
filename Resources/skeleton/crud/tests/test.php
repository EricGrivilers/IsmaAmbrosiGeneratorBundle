<?php

namespace {{ namespace }}\Tests\Controller{{ document_namespace ? '\\' ~ document_namespace : '' }};

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class {{ document_class }}ControllerTest extends WebTestCase
{
    /*

{%- if 'new' in actions %}
    {%- include 'tests/others/full_scenario.php' -%}
{%- else %}
    {%- include 'tests/others/short_scenario.php' -%}
{%- endif %}

    */
}