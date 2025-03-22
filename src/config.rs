use std::ops::Range;

pub const DEFAULT_MIME_TYPES: &[(&str, &str)] = &[("image/png", "png")];

pub const DEFAULT_GALLERY_PAGE: i64 = 1;
pub const DEFAULT_GALLERY_SIZE: i64 = 25;
pub const DEFAULT_GALLERY_SIZE_RANGE: Range<i64> = 1..50;
