<?php

declare(strict_types=1);

namespace Sisow\Payment\Storefront\Controller;

use Sisow\Payment\Helpers\RedirectHelper;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

class RedirectController
{
    /** @var RedirectHelper */
    private $redirectHelper;

    public function __construct(RedirectHelper $redirectHelper)
    {
        $this->redirectHelper = $redirectHelper;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/sisow/redirect", name="sisow_redirect", defaults={"csrf_protected": false})
     */
    public function execute(Request $request): Response
    {
        $params = $request->query->all();
        $hash = $request->get('hash');

        unset($params['hash']);

        if (empty($hash)) {
            throw new NotFoundHttpException();
        }

        try {
            $target = $this->redirectHelper->decode($hash);
        } catch (Throwable $exception) {
            throw new NotFoundHttpException();
        }


        $query_url = '';
        foreach ($params AS $key=>$value)
            $query_url .= '&'.$key.'='.$value;

        return new RedirectResponse($target.$query_url);
    }
}
