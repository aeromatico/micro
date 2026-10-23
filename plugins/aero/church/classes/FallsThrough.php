<?php namespace Aero\Church\Classes;

/**
 * Las páginas de Iglesia (/noticias, /eventos, /bienvenida…) usan URLs corrientes. Si la sección NO está activa
 * para el dominio, la URL sigue siendo de la página normal del sitio (`/:slug`) en vez de dar 404: así un sitio
 * que tiene su propia página «noticias» no la pierde.
 */
trait FallsThrough
{
    protected function fallThrough()
    {
        $segments = array_values(array_filter(explode('/', trim(request()->path(), '/'))));
        if (count($segments) !== 1) {
            return $this->controller->run('404');
        }

        $page = \Cms\Classes\Page::load($this->controller->getTheme(), 'page');
        if (!$page) {
            return $this->controller->run('404');
        }

        $this->controller->getRouter()->setParameters(['slug' => $segments[0]]);

        return $this->controller->runPage($page);
    }
}
