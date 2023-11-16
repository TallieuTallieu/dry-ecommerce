<?php

namespace Tnt\Ecommerce\Cart;

use dry\db\FetchException;
use Oak\Session\Session;
use Tnt\Ecommerce\Contracts\CartStorageInterface;
use Tnt\Ecommerce\Contracts\StorableInterface;
use Tnt\Ecommerce\Model\Cart;

class SessionCartStorage implements CartStorageInterface
{
    /**
     * @var Session $session
     */
    private $session;

    /**
     * SessionCartStorage constructor.
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * @param StorableInterface $cart
     * @return mixed|void
     */
    public function store(StorableInterface $cart)
    {
        $this->session->set('cart', $cart->getIdentifier());
        $this->session->save();
    }

    /**
     * @return null|StorableInterface
     */
    public function retrieve(): ?StorableInterface
    {
        try {
            return Cart::load($this->session->get('cart'));
        } catch (FetchException $e) {
            //
        }

        return null;
    }

    /**
     * @return mixed|void
     */
    public function clear()
    {
        $this->session->set('cart', null);
        $this->session->save();
    }
}