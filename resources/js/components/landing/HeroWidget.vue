<template>
    <div
        id="hero"
        class="flex flex-col overflow-hidden px-6 pt-6 lg:px-20"
        style="
            background:
                linear-gradient(
                    0deg,
                    rgba(255, 255, 255, 0.2),
                    rgba(255, 255, 255, 0.2)
                ),
                radial-gradient(
                    77.36% 256.97% at 77.36% 57.52%,
                    rgb(238, 239, 175) 0%,
                    rgb(195, 227, 250) 100%
                );
            clip-path: ellipse(150% 87% at 93% 13%);
        "
    >
        <div class="mx-6 mt-0 md:mx-20 md:mt-6">
            <h1 class="text-6xl leading-tight font-bold text-gray-900">
                <span class="block font-light">Ministry of Finance</span>
                E-Document Financial System
            </h1>
            <p
                class="text-2xl leading-normal font-normal text-gray-700 md:mt-4"
            >
                Edo State, Benin City...
            </p>
            <Button
                label="Get Started"
                as="router-link"
                to="/"
                rounded
                class="mt-8 !px-4 !text-xl"
            ></Button>
        </div>
        <!-- <div class="flex justify-center md:justify-end">
            <img src="/demo/images/landing/screen-1.png" alt="Hero Image" class="w-9/12 md:w-auto" />
        </div> -->
    </div>
</template>
