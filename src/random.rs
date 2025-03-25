use rand::{Rng, rng};

pub fn generate_random_sequence(size: usize, char_pool: &String) -> String {
    let char_pool = char_pool.as_bytes();
    let mut rng = rng();
    let mut output = String::new();

    for _ in 0..size {
        let index = rng.random_range(0..char_pool.len());
        output.push(char_pool[index] as char);
    }

    output
}
