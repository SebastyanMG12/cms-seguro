<?php

namespace Tests\Unit;

use App\Rules\PersonName;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PersonNameValidationTest extends TestCase
{
    private function validateName(mixed $value, int $min = 2, int $max = 100): bool
    {
        $validator = Validator::make(
            ['name' => $value],
            ['name' => ['required', new PersonName($min, $max)]]
        );

        return $validator->passes();
    }

    public function test_rejects_numbers_only(): void
    {
        $this->assertFalse($this->validateName('12345'));
        $this->assertFalse($this->validateName('9876543210'));
    }

    public function test_rejects_symbols_only(): void
    {
        $this->assertFalse($this->validateName('###'));
        $this->assertFalse($this->validateName('$$$%%%'));
        $this->assertFalse($this->validateName('.-_'));
        $this->assertFalse($this->validateName('.-’'));
    }

    public function test_rejects_html_or_script_tags(): void
    {
        $this->assertFalse($this->validateName('<script>'));
        $this->assertFalse($this->validateName('<img src=x>'));
    }

    public function test_rejects_only_spaces(): void
    {
        $this->assertFalse($this->validateName('   '));
        $this->assertFalse($this->validateName("\t\n"));
    }

    public function test_rejects_single_character(): void
    {
        $this->assertFalse($this->validateName('A'));
        $this->assertFalse($this->validateName('1'));
        $this->assertFalse($this->validateName('.'));
    }

    public function test_accepts_valid_names_with_unicode_accents_and_punctuation(): void
    {
        $this->assertTrue($this->validateName('María José'));
        $this->assertTrue($this->validateName("O'Brien"));
        $this->assertTrue($this->validateName('Ana-María'));
        $this->assertTrue($this->validateName('Núñez'));
        $this->assertTrue($this->validateName('José Ángel'));
        $this->assertTrue($this->validateName('Jean-Luc Picard'));
        $this->assertTrue($this->validateName('D’Angelo'));
    }

    public function test_rejects_excessive_length(): void
    {
        $tooLong = str_repeat('A', 101);
        $this->assertFalse($this->validateName($tooLong, 2, 100));
    }

    public function test_rejects_repeated_special_characters(): void
    {
        $this->assertFalse($this->validateName('Maria...Jose'));
        $this->assertFalse($this->validateName('Ana--Maria'));
        $this->assertFalse($this->validateName("O''Brien"));
    }
}
