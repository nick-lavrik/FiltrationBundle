<?php

namespace ITE\FiltrationBundle\Filtration;

use ITE\FiltrationBundle\Event\FiltrationEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class FiltrationManager
 *
 * @author sam0delkin <t.samodelkin@gmail.com>
 */
class FiltrationManager
{
    /**
     * @var FilterInterface[]
     */
    private $filters = [];

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @param FormFactoryInterface $formFactory
     * @param RequestStack $requestStack
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(FormFactoryInterface $formFactory, RequestStack $requestStack,
        EventDispatcherInterface $eventDispatcher)
    {
        $this->formFactory = $formFactory;
        $this->requestStack = $requestStack;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param mixed $target
     * @param string|FilterInterface $filter
     * @return mixed
     */
    public function filter($target, $filter)
    {
        if (is_string($filter)) {
            $filter = $this->getFilter($filter);
        }

        $form = $this->getFilterForm($filter->getName());

        if ($form->isValid()) {
            foreach ($form as $child) {
                $target = $this->filterPart($child, $target, $filter);
            }
        }

        return $target;
    }

    /**
     * @param FormInterface   $form
     * @param mixed           $target
     * @param FilterInterface $filter
     * @return \Doctrine\Common\Collections\ArrayCollection|\Doctrine\ORM\QueryBuilder
     */
    private function filterPart(FormInterface $form, $target, FilterInterface $filter)
    {
        $event = new FiltrationEvent($form, $target);
        $this->eventDispatcher->dispatch(FiltrationEvent::EVENT_NAME, $event);

        if ($event->isTargetModified()) {
            $filter->markFieldModified($event->getFieldName());
        }

        return $event->getTarget();
    }

    /**
     * @param $name
     * @return \ITE\FiltrationBundle\Filtration\FilterInterface
     */
    public function getFilter($name)
    {
        if (!isset($this->filters[$name])) {
            throw new \InvalidArgumentException(sprintf('Filter "%s" was not found.', $name));
        }

        return $this->filters[$name];
    }

    /**
     * @param $name
     * @return FormInterface
     */
    public function getFilterForm($name)
    {
        $filter = $this->getFilter($name);

        $form = $filter->getFilterForm($this->formFactory);
        $request = $this->requestStack->getMasterRequest();

        if ($request->query->has($form->getName())) {
            $form->submit($request->query->get($form->getName()));
        }

        return $form;
    }

    /**
     * @param FilterInterface $filter
     */
    public function addFilter(FilterInterface $filter)
    {
        if (isset($this->filters[$filter->getName()])) {
            throw new \InvalidArgumentException(sprintf('Filter "%s" has been already registered.', $filter->getName()));
        }

        $this->filters[$filter->getName()] = $filter;
    }

    /**
     * @return FilterInterface[]
     */
    public function getFilters()
    {
        return $this->filters;
    }
} 