<?php

namespace App\Tests\Functional;

use App\Entity\Cv;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PublicCvTest extends WebTestCase
{
    private KernelBrowser $client;
    private int $cvId;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($entityManager))->createSchema([
            $entityManager->getClassMetadata(Cv::class),
            $entityManager->getClassMetadata(User::class),
        ]);

        $cv = (new Cv())
            ->setName('Public CV')
            ->setTitle('Developer')
            ->setLocation('Paris')
            ->setSkills(['PHP', 'Symfony'])
            ->setExperiences([['title' => 'Developer', 'company' => 'Example']])
            ->setCreatedAt(new \DateTime('2026-01-01 12:00:00'));
        $entityManager->persist($cv);
        $entityManager->flush();
        $this->cvId = $cv->getId();
    }

    public function testRestReadsArePublicAndOnlyExposePublicFields(): void
    {
        $this->client->request('GET', '/api/cvs', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $collection = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(1, $collection);
        self::assertSame($this->cvId, $collection[0]['id']);

        $this->client->request('GET', '/api/cvs/'.$this->cvId, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $cv = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(
            ['id', 'name', 'title', 'locale', 'location', 'skills', 'languages', 'education', 'experiences', 'createdAt'],
            array_keys($cv),
        );
        self::assertSame(['PHP', 'Symfony'], $cv['skills']);
        self::assertSame('fr', $cv['locale']);
        self::assertSame([], $cv['languages']);
        self::assertSame([], $cv['education']);
        self::assertSame([['title' => 'Developer', 'company' => 'Example']], $cv['experiences']);

        $this->client->request('GET', '/api/cvs/'.$this->cvId, server: ['HTTP_ACCEPT' => 'application/ld+json']);
        self::assertResponseIsSuccessful();
        $cv = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('/api/cvs/'.$this->cvId, $cv['@id']);
        self::assertSame('Cv', $cv['@type']);
    }

    public function testCvHtmlRoutesAreNotAvailable(): void
    {
        foreach (['/', '/cv/'.$this->cvId] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testIndividualCvOffersHtml(): void
    {
        foreach (['/api/cvs/'.$this->cvId, '/api/cvs/'.$this->cvId.'.html'] as $path) {
            $this->client->request('GET', $path, server: ['HTTP_ACCEPT' => 'text/html']);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('content-type', 'text/html; charset=UTF-8');
            $html = $this->client->getResponse()->getContent();
            foreach (['<h1>Public CV</h1>', 'Developer', 'Paris', 'PHP', 'Symfony', 'Example'] as $text) {
                self::assertStringContainsString($text, $html);
            }
            self::assertStringNotContainsString('swagger-ui', $html);
        }

        $this->client->request('HEAD', '/api/cvs/'.$this->cvId.'.html');
        self::assertResponseIsSuccessful();
        self::assertSame('', $this->client->getResponse()->getContent());

        $this->client->request('GET', '/api/cvs/0.html');
        self::assertResponseStatusCodeSame(404);
    }

    public function testHtmlEscapesCvContentAndDisplaysNewFields(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $cv = $entityManager->find(Cv::class, $this->cvId);
        $cv->setName('<script>alert(1)</script>')
            ->setLanguages(['French'])
            ->setEducation(['Degree'])
            ->setGithub('https://github.com/example')
            ->setLinkedin('https://linkedin.com/in/example')
            ->setPortfolio('javascript:alert(1)')
            ->setExperiences([['company' => '<img src=x onerror=alert(1)>', 'tasks' => ['Nested task']]]);
        $entityManager->flush();
        $this->client->request('GET', '/api/cvs/'.$this->cvId.'.html');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        foreach (['French', 'Degree', 'https://github.com/example', 'https://linkedin.com/in/example', 'Nested task', '&lt;script&gt;', '&lt;img'] as $text) {
            self::assertStringContainsString($text, $html);
        }
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('href="javascript:', $html);
    }

    public function testHtmlFormatsFrenchExperienceLabelsWithoutChangingJson(): void
    {
        $experiences = [[
            'entreprise' => 'Example',
            'poste' => 'Developer',
            'anneeDebut' => '2025',
            'anneeFin' => '2026',
            'missions' => ['Development'],
            'technologies' => ['PHP'],
        ]];
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->find(Cv::class, $this->cvId)->setExperiences($experiences);
        $entityManager->flush();

        $this->client->request('GET', '/api/cvs/'.$this->cvId.'.html');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertMatchesRegularExpression('/<li>\s*<strong>Example<\/strong>/', $html);
        self::assertStringNotContainsString('<dt>Entreprise</dt>', $html);
        foreach (['Poste', 'Début', 'Fin', 'Missions', 'Technologies'] as $label) {
            self::assertStringContainsString('<dt>'.$label.'</dt>', $html);
        }
        self::assertStringNotContainsString('anneeDebut', $html);
        self::assertStringNotContainsString('anneeFin', $html);

        $this->client->request('GET', '/api/cvs/'.$this->cvId, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertSame($experiences, $this->responseData()['experiences']);
    }

    public function testRestWritesRequireAuthentication(): void
    {
        foreach ([
            ['POST', '/api/cvs'],
            ['PUT', '/api/cvs/'.$this->cvId],
            ['PATCH', '/api/cvs/'.$this->cvId],
            ['DELETE', '/api/cvs/'.$this->cvId],
        ] as [$method, $path]) {
            $this->client->request($method, $path, server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
            self::assertResponseStatusCodeSame(401);
        }
    }

    public function testGraphQlReadsArePublicAndPreserveNestedData(): void
    {
        $this->graphql('{ cvs { edges { node { id name title locale location skills experiences createdAt } } } }');
        $result = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('errors', $result);
        $cv = $result['data']['cvs']['edges'][0]['node'];
        self::assertSame('Public CV', $cv['name']);
        self::assertSame('fr', $cv['locale']);
        self::assertSame(['PHP', 'Symfony'], $cv['skills']);
        self::assertSame([['title' => 'Developer', 'company' => 'Example']], $cv['experiences']);

        $this->graphql(sprintf('{ cv(id: "/api/cvs/%d") { id name } }', $this->cvId));
        $result = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('errors', $result);
        self::assertSame('Public CV', $result['data']['cv']['name']);
    }

    public function testGraphQlMutationsRequireAdminAndUsersAreNotExposed(): void
    {
        $this->graphql('{ __schema { mutationType { name } queryType { fields { name } } } }');
        $result = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('errors', $result);
        self::assertSame('Mutation', $result['data']['__schema']['mutationType']['name']);
        $fields = array_column($result['data']['__schema']['queryType']['fields'], 'name');
        self::assertContains('cv', $fields);
        self::assertContains('cvs', $fields);
        self::assertNotContains('user', $fields);
        self::assertNotContains('users', $fields);

        $this->graphql('mutation { deleteCv(input: {id: "/api/cvs/'.$this->cvId.'"}) { clientMutationId } }');
        $result = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('errors', $result);
        $this->assertOriginalCvUnchanged();
    }

    public function testDocumentationIsPublicAndDocumentsCrud(): void
    {
        $this->client->request('GET', '/api/docs.jsonopenapi');
        self::assertResponseIsSuccessful();
        $docs = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertEqualsCanonicalizing(['get', 'post'], array_keys($docs['paths']['/api/cvs']));
        self::assertEqualsCanonicalizing(['get', 'put', 'patch', 'delete'], array_keys($docs['paths']['/api/cvs/{id}']));
        self::assertArrayNotHasKey('/api/users', $docs['paths']);
    }

    public function testPublicEntryPointsAreAccessible(): void
    {
        foreach (['/api', '/api/docs', '/api/contexts/Cv', '/api/graphql/graphiql'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
        }

        $this->client->request('GET', '/api/graphql', parameters: ['query' => '{ cvs { totalCount } }']);
        self::assertResponseIsSuccessful();
        $result = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('errors', $result);
        self::assertSame(1, $result['data']['cvs']['totalCount']);
    }

    public function testUsersAreNotExposedOverRest(): void
    {
        foreach (['/api/users', '/api/users/1'] as $path) {
            $this->client->request('GET', $path, server: ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'email' => 'missing@example.test',
            'password' => 'invalid-password',
        ]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testOrdinaryUserCannotWriteRestOrGraphQl(): void
    {
        $this->authenticate([]);
        $this->assertWritesDenied(403);
    }

    public function testAnonymousCannotRunAnyGraphQlMutation(): void
    {
        $this->assertGraphQlWritesDenied();
    }

    public function testAdminCanPerformRestCrud(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $this->client->jsonRequest('POST', '/api/cvs', [
            'name' => 'New CV',
            'title' => 'Engineer',
            'skills' => ['PHP'],
            'experiences' => [['company' => 'Example', 'title' => 'Engineer']],
            'createdAt' => '2000-01-01T00:00:00+00:00',
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->responseData();
        self::assertSame('New CV', $created['name']);
        self::assertNotEmpty($created['createdAt']);
        self::assertNotSame('2000-01-01', substr($created['createdAt'], 0, 10));
        $path = '/api/cvs/'.$created['id'];

        $this->client->request('GET', $path, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertSame(['PHP'], $this->responseData()['skills']);

        $this->client->jsonRequest('PUT', $path, [
            'name' => 'Updated CV',
            'title' => 'Lead engineer',
            'location' => 'Paris',
            'skills' => ['Symfony'],
            'experiences' => [],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('Updated CV', $this->responseData()['name']);

        $this->client->request('PATCH', $path, server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: '{"location":null}');
        self::assertResponseIsSuccessful();
        self::assertArrayNotHasKey('location', $this->responseData());
        self::assertSame('Updated CV', $this->responseData()['name']);

        $this->client->request('DELETE', $path);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', $path, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminRestWritesValidateRequiredFields(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        foreach ([[], ['name' => '', 'title' => 'Engineer'], ['name' => str_repeat('a', 256), 'title' => 'Engineer']] as $payload) {
            $this->client->jsonRequest('POST', '/api/cvs', $payload);
            self::assertResponseStatusCodeSame(422);
        }
        $this->assertOriginalCvUnchanged();
    }

    public function testAdminCanWriteNewFieldsAndTheyArePublic(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $fields = [
            'locale' => 'en',
            'languages' => ['French: native', 'English: professional'],
            'education' => ['Software development degree'],
            'github' => 'https://github.com/example',
            'linkedin' => 'https://www.linkedin.com/in/example',
            'portfolio' => 'https://example.com',
        ];
        $this->client->jsonRequest('POST', '/api/cvs', ['name' => 'New CV', 'title' => 'Developer'] + $fields);
        self::assertResponseStatusCodeSame(201);
        $path = '/api/cvs/'.$this->responseData()['id'];
        foreach ($fields as $key => $value) {
            self::assertSame($value, $this->responseData()[$key]);
        }

        $this->client->jsonRequest('PUT', $path, ['name' => 'Updated', 'title' => 'Developer'] + $fields);
        self::assertResponseIsSuccessful();
        $this->client->setServerParameter('HTTP_AUTHORIZATION', '');
        $this->client->request('GET', $path, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        foreach ($fields as $key => $value) {
            self::assertSame($value, $this->responseData()[$key]);
        }

        $this->graphql('{ cv(id: "'.$path.'") { locale languages education github linkedin portfolio } }');
        $result = $this->responseData();
        self::assertArrayNotHasKey('errors', $result);
        self::assertSame($fields, $result['data']['cv']);
    }

    public function testAdminCanClearNewFieldsWithPatch(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $path = '/api/cvs/'.$this->cvId;
        foreach ([
            ['languages' => ['English'], 'education' => ['Degree'], 'github' => 'https://github.com/example', 'linkedin' => 'https://linkedin.com/in/example', 'portfolio' => 'https://example.com'],
            ['languages' => [], 'education' => [], 'github' => null, 'linkedin' => null, 'portfolio' => null],
        ] as $fields) {
            $this->client->request('PATCH', $path, server: [
                'CONTENT_TYPE' => 'application/merge-patch+json',
                'HTTP_ACCEPT' => 'application/json',
            ], content: json_encode($fields, JSON_THROW_ON_ERROR));
            self::assertResponseIsSuccessful();
            $result = $this->responseData();
            foreach ($fields as $key => $value) {
                if ($value === null) {
                    self::assertArrayNotHasKey($key, $result);
                } else {
                    self::assertSame($value, $result[$key]);
                }
            }
        }
    }

    public function testNewFieldsAreValidated(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        foreach ([
            ['locale' => 'de'],
            ['locale' => 'en-US'],
            ['locale' => ''],
            ['github' => 'not-a-url'],
            ['linkedin' => 'javascript:alert(1)'],
            ['portfolio' => 'ftp://example.com'],
            ['portfolio' => 'https://example.com/'.str_repeat('a', 2048)],
            ['languages' => ['']],
            ['languages' => [123]],
            ['education' => ['']],
            ['education' => [123]],
        ] as $fields) {
            $this->client->jsonRequest('POST', '/api/cvs', ['name' => 'Invalid', 'title' => 'Developer'] + $fields);
            self::assertResponseStatusCodeSame(422);
        }
        $this->assertOriginalCvUnchanged();
    }

    public function testLocaleCanBeChangedWithPatchAndTranslatesHtml(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $this->client->request('PATCH', '/api/cvs/'.$this->cvId, server: [
            'CONTENT_TYPE' => 'application/merge-patch+json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: json_encode([
            'locale' => 'en',
            'languages' => ['English'],
            'education' => ['Degree'],
            'experiences' => [['entreprise' => 'Example', 'poste' => 'Developer', 'anneeDebut' => '2025', 'missions' => ['Development']]],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        self::assertSame('en', $this->responseData()['locale']);

        $this->client->request('GET', '/api/cvs/'.$this->cvId.'.html');
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        foreach (['<html lang="en">', '<h2>Skills</h2>', '<h2>Languages</h2>', '<h2>Education</h2>', '<h2>Experience</h2>', '<dt>Role</dt>', '<dt>Start</dt>', '<dt>Responsibilities</dt>', 'Published on'] as $label) {
            self::assertStringContainsString($label, $html);
        }
        self::assertStringNotContainsString('Compétences', $html);
    }

    public function testAdminCanPerformGraphQlCrud(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $this->graphql('mutation { createCv(input: {name: "GraphQL CV", title: "Engineer", locale: "en", skills: ["PHP"], experiences: [{company: "Example", title: "Engineer"}]}) { cv { id name locale skills experiences createdAt } } }');
        $result = $this->responseData();
        self::assertArrayNotHasKey('errors', $result);
        $cv = $result['data']['createCv']['cv'];
        self::assertSame('GraphQL CV', $cv['name']);
        self::assertSame('en', $cv['locale']);
        self::assertNotEmpty($cv['createdAt']);
        self::assertSame(['PHP'], $cv['skills']);
        self::assertSame([['company' => 'Example', 'title' => 'Engineer']], $cv['experiences']);
        $id = $cv['id'];

        $this->graphql('mutation { updateCv(input: {id: "'.$id.'", title: "Lead"}) { cv { id name title } } }');
        $result = $this->responseData();
        self::assertArrayNotHasKey('errors', $result);
        self::assertSame('Lead', $result['data']['updateCv']['cv']['title']);
        self::assertSame('GraphQL CV', $result['data']['updateCv']['cv']['name']);

        $this->graphql('mutation { deleteCv(input: {id: "'.$id.'"}) { cv { id } } }');
        self::assertArrayNotHasKey('errors', $this->responseData());
        $this->client->request('GET', $id, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminGraphQlWritesValidateRequiredFields(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $this->graphql('mutation { createCv(input: {name: "", title: "Engineer"}) { cv { id } } }');
        self::assertArrayHasKey('errors', $this->responseData());
        $this->assertOriginalCvUnchanged();
    }

    public function testGraphQlRejectsUnsupportedLocale(): void
    {
        $this->authenticate(['ROLE_ADMIN']);
        $this->graphql('mutation { createCv(input: {name: "Invalid", title: "Developer", locale: "de"}) { cv { id } } }');
        self::assertArrayHasKey('errors', $this->responseData());
        $this->assertOriginalCvUnchanged();
    }

    public function testInvalidJwtCannotWrite(): void
    {
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer invalid-token');
        $this->client->jsonRequest('POST', '/api/cvs', ['name' => 'Forbidden', 'title' => 'Forbidden']);
        self::assertResponseStatusCodeSame(401);
    }

    private function authenticate(array $roles): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('account@example.test')->setRoles($roles);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'test-password-123'));
        $entityManager->persist($user);
        $entityManager->flush();

        $this->client->jsonRequest('POST', '/api/login', [
            'email' => 'account@example.test',
            'password' => 'test-password-123',
        ]);
        self::assertResponseIsSuccessful();
        $this->client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->responseData()['token']);
    }

    private function assertWritesDenied(int $status): void
    {
        foreach ([
            ['POST', '/api/cvs'],
            ['PUT', '/api/cvs/'.$this->cvId],
            ['PATCH', '/api/cvs/'.$this->cvId],
            ['DELETE', '/api/cvs/'.$this->cvId],
        ] as [$method, $path]) {
            $this->client->jsonRequest($method, $path, ['name' => 'Forbidden', 'title' => 'Forbidden']);
            self::assertResponseStatusCodeSame($status);
        }
        $this->assertGraphQlWritesDenied();
    }

    private function assertGraphQlWritesDenied(): void
    {
        foreach ([
            'createCv(input: {name: "Forbidden", title: "Forbidden"})',
            'updateCv(input: {id: "/api/cvs/'.$this->cvId.'", name: "Forbidden"})',
            'deleteCv(input: {id: "/api/cvs/'.$this->cvId.'"})',
        ] as $mutation) {
            $this->graphql('mutation { '.$mutation.' { cv { id } } }');
            $result = $this->responseData();
            self::assertArrayHasKey('errors', $result);
            self::assertStringContainsString('Access Denied', $result['errors'][0]['message']);
        }
        $this->assertOriginalCvUnchanged();
    }

    private function assertOriginalCvUnchanged(): void
    {
        $this->client->request('GET', '/api/cvs', server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $collection = $this->responseData();
        self::assertCount(1, $collection);
        self::assertSame('Public CV', $collection[0]['name']);
    }

    private function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function graphql(string $query): void
    {
        $this->client->request(
            'POST',
            '/api/graphql',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(['query' => $query], JSON_THROW_ON_ERROR),
        );
        self::assertResponseIsSuccessful();
    }
}
