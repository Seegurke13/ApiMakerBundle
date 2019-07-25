<?= "<?php\n" ?>

namespace <?= $namespace ?>;

use <?= $entity_full_class_name ?>;
use <?= $form_full_class_name ?>;
<?php if (isset($repository_full_class_name)): ?>
use <?= $repository_full_class_name ?>;
<?php endif ?>
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @Route("<?= $route_path ?>")
 */
class <?= $class_name ?>
{
    private $<?= $repository_var ?>;
    private $entityManager;
    private $serializer;
    private $formFactory;

    public function __construct(
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory,
        <?= $repository_class_name ?> $<?= $repository_var ?>
    ) {
        $this-><?= $repository_var ?> = $<?= $repository_var ?>;
        $this->serializer = $serializer;
        $this->entityManager = $entityManager;
        $this->formFactory = $formFactory;
    }

    /**
     * @Route("/", name="<?= $route_name ?>_index", methods={"GET"})
     */

    public function index(): Response
    {
        $<?= $entity_var_plural ?> = $this-><?= $repository_var ?>->findAll();

        return new JsonResponse($this->serializer->serialize($<?= $entity_var_plural ?>, 'json'));
    }

    /**
     * @Route("/new", name="<?= $route_name ?>_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $<?= $entity_var_plural ?> = new <?= $entity_class_name ?>();

        $form = $this->formFactory->create(<?= $form_class_name ?>::class, $<?= $entity_var_plural ?>);
        $form->submit(json_decode($request->getContent(), true));

        if ($form->isValid() === true) {
            $this->entityManager->persist($<?= $entity_var_plural ?>);
            $this->entityManager->flush();

            return new JsonResponse(['state' => 'success']);
        }

        return new JsonResponse([
            'status' => 'error',
            'error' => $form->getErrors()
        ], 500);
    }

    /**
     * @Route("/{<?= $entity_identifier ?>}", name="<?= $route_name ?>_show", methods={"GET"})
     */
    public function show(<?= $entity_class_name ?> $<?= $entity_var_singular ?>): Response
    {
        return new JsonResponse($this->serializer->serialize($<?= $entity_var_singular ?>, 'json'));
    }

    /**
     * @Route("/{<?= $entity_identifier ?>}/edit", name="<?= $route_name ?>_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, <?= $entity_class_name ?> $<?= $entity_var_singular ?>): Response
    {
        $form = $this->formFactory->create(<?= $form_class_name ?>::class, $<?= $entity_var_singular ?>);
        $form->submit(json_decode($request->getContent(), true));

        if ($form->isValid() === true) {
            $this->entityManager->flush();

            return new JsonResponse(['state' => 'success']);
        }

        return new JsonResponse([
            'status' => 'error',
            'error' => $form->getErrors()
        ], 500);
    }

    /**
     * @Route("/{<?= $entity_identifier ?>}", name="<?= $route_name ?>_delete", methods={"DELETE"})
     */
    public function delete(<?= $entity_class_name ?> $<?= $entity_var_singular ?>): Response
    {
        $this->entityManager->remove($<?= $entity_var_singular ?>);
        $this->entityManager->flush();

        return new JsonResponse(['state' => 'success']);
    }
}
