<?php

namespace Knp\Bundle\KnpBundlesBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Knp\Bundle\KnpBundlesBundle\Entity\Bundle;

class KbRemoveBundleCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('kb:remove:bundle')
            ->addArgument('bundleName')
            ->setDescription('Removes almost all informations about bundle, and marks it as deleted.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundle = $this->getContainer()
            ->get('knp_bundles.entity_manager')
            ->getRepository('Knp\Bundle\KnpBundlesBundle\Entity\Bundle')
            ->findOneByName($input->getArgument('bundleName'));
        if (!$bundle) {
            $output->writeln(sprintf('[%s] Bundle "<error>%s</error>" was not found', date('d-m-y H:i:s'), $input->getArgument('bundleName')));

            return 1;
        }

        $this->cleanupBundle($bundle);
        $this->removeBundleFromIndex($bundle);

        $output->writeln(sprintf('[%s] Bundle "<comment>%s</comment>" was marked as removed', date('d-m-y H:i:s'), $bundle->getFullName()));

        return 0;
    }

    /**
     * @param Bundle $bundle
     */
    private function cleanupBundle(Bundle $bundle)
    {
        $em = $this->getContainer()->get('knp_bundles.entity_manager');

        $bundle->setState(Bundle::STATE_DELETED_BY_OWNER);
        $bundle->fromArray(array(
            'description' => null,
            'homepage' => null,
            'score' => 0,
            'scoreDetails' => null,
            'trend1' => 0,
            'nbFollowers' => 0,
            'nbForks' => 0,
            'nbRecommenders' => 0,
            'tags' => array(),
            'contributors' => array(),
            'canonicalConfig' => null,
            'lastCommits' => array(),
            'readme' => null,
            'license' => null,
            'travisCiBuildStatus' => null,
            'composerName' => null,
            'symfonyVersions' => null,
        ));

        foreach ($bundle->getKeywords() as $keyword) {
            $bundle->removeKeyword($keyword);
            $em->persist($keyword);
        }

        foreach ($bundle->getRecommenders() as $user) {
            $bundle->removeRecommender($user);
            $em->persist($user);
        }

        $em->persist($bundle);
        $em->flush();
    }

    /**
     * @param Bundle $bundle
     */
    private function removeBundleFromIndex(Bundle $bundle)
    {
        if ($this->getContainer()->get('knp_bundles.utils.solr')->isSolrRunning()) {
            $indexer = $this->getContainer()->get('knp_bundles.indexer.solr');
            $indexer->deleteBundlesIndex($bundle);
        }
    }
}
