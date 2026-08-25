@php
    $heroSlides = [
        [
            'id' => 0,
            'tab' => 'AI-VET Platform',
            'title' => 'BisaBelajar — AI-VET',
            'desc1' => 'Wujudkan pembelajaran vokasi dan pemrograman berbasis kecerdasan buatan adaptif dengan kurikulum industri.',
            'desc2' => 'Dirancang dengan arsitektur Course → Module → Learning Unit untuk penguasaan kompetensi nyata.',
            'buttonText' => 'Rasakan sekarang',
            'buttonUrl' => '#about',
            'videoWebm' => asset('videos/vid1.webm'),
            'videoMp4' => asset('videos/vid1.mp4'),
            'videoPosition' => 'object-right lg:object-center',
        ],
        [
            'id' => 1,
            'tab' => 'Interactive Sandbox',
            'title' => 'Interactive Sandbox',
            'desc1' => 'Latihan coding langsung dengan eksekusi aman dan terisolasi. Kode siswa dieksekusi di sandbox mandiri.',
            'desc2' => 'Sistem evaluasi berbasis ketuntasan materi (Progress is not mastery), mengukur kecakapan nyata.',
            'buttonText' => 'Coba sandbox',
            'buttonUrl' => route('login'),
            'videoWebm' => asset('videos/vid2.webm'),
            'videoMp4' => asset('videos/vid2.mp4'),
            'videoPosition' => 'object-right lg:object-center',
        ],
    ];
@endphp

<section 
    x-data="{
        activeSlide: 0,
        totalSlides: {{ count($heroSlides) }},
        videoLoaded: {},
        
        init() {
            this.playSlide(0);
            this.$watch('activeSlide', (newIndex) => {
                this.playSlide(newIndex);
            });
        },
        
        setSlide(index) {
            this.activeSlide = index;
            this.playSlide(index);
        },
        
        nextSlide() {
            this.activeSlide = (this.activeSlide + 1) % this.totalSlides;
        },
        
        playSlide(index) {
            this.$nextTick(() => {
                const vids = this.$el.querySelectorAll('video');
                vids.forEach((vid, i) => {
                    if (i === index) {
                        vid.currentTime = 0;
                        const playPromise = vid.play();
                        if (playPromise !== undefined) {
                            playPromise.catch(() => {});
                        }
                    } else {
                        vid.pause();
                        vid.currentTime = 0;
                    }
                });
            });
        }
    }"
    class="w-full bg-slate-100/70 dark:bg-slate-950 font-sans text-slate-900 dark:text-slate-100 overflow-hidden transition-colors duration-200"
>
    <div class="relative mx-auto max-w-[1920px] overflow-hidden bg-slate-100/70 dark:bg-slate-950 min-h-[380px] sm:min-h-[420px] lg:min-h-[480px] flex flex-col justify-between transition-colors duration-200">
        <div 
            class="absolute inset-0 z-0 overflow-hidden pointer-events-none opacity-85 dark:opacity-40"
            style="mask-image: linear-gradient(to right, rgba(0,0,0,0) 0%, rgba(0,0,0,0.5) 15%, rgba(0,0,0,1) 40%, rgba(0,0,0,1) 94%, rgba(0,0,0,0) 100%); -webkit-mask-image: linear-gradient(to right, rgba(0,0,0,0) 0%, rgba(0,0,0,0.5) 15%, rgba(0,0,0,1) 40%, rgba(0,0,0,1) 94%, rgba(0,0,0,0) 100%);"
        >
            @foreach ($heroSlides as $index => $slide)
                <div 
                    x-show="activeSlide === {{ $index }}"
                    x-transition:enter="transition-opacity ease-out duration-1000"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition-opacity ease-in duration-500 absolute inset-0"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 h-full w-full"
                >
                    <video 
                        muted 
                        playsinline 
                        preload="auto" 
                        @ended="nextSlide()"
                        @loadeddata="videoLoaded[{{ $index }}] = true"
                        @playing="videoLoaded[{{ $index }}] = true"
                        class="h-full w-full object-cover {{ $slide['videoPosition'] ?? 'object-right lg:object-center' }} transition-opacity duration-1000 ease-in-out opacity-0"
                        :class="videoLoaded[{{ $index }}] ? 'opacity-100' : 'opacity-0'"
                    >
                        <source src="{{ $slide['videoWebm'] }}" type="video/webm">
                        <source src="{{ $slide['videoMp4'] }}" type="video/mp4">
                    </video>
                </div>
            @endforeach
        </div>

        <div class="relative z-10 mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 pt-7 sm:pt-9 lg:pt-12 pb-6 sm:pb-8">
            <div class="max-w-xl lg:max-w-2xl min-h-[220px] sm:min-h-[240px] flex flex-col justify-center">
                @foreach ($heroSlides as $index => $slide)
                    <div 
                        x-show="activeSlide === {{ $index }}"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150 absolute"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0 -translate-y-1"
                    >
                        <h1 class="text-2xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-slate-900 dark:text-white mb-2 sm:mb-4 font-sans leading-tight">
                            {{ $slide['title'] }}
                        </h1>

                        <div class="space-y-1 text-slate-700 dark:text-slate-300 text-xs sm:text-sm lg:text-[15px] leading-relaxed mb-4 max-w-xl font-normal line-clamp-3 sm:line-clamp-none">
                            <p>{{ $slide['desc1'] }}</p>
                            <p class="hidden sm:block text-slate-500 dark:text-slate-400 text-xs sm:text-sm">{{ $slide['desc2'] }}</p>
                        </div>

                        <div class="flex lg:hidden items-center gap-1.5 mb-5 mt-1">
                            @foreach ($heroSlides as $dotIdx => $dotSlide)
                                <button 
                                    type="button" 
                                    @click="setSlide({{ $dotIdx }})"
                                    class="h-1.5 rounded-full transition-all duration-200 focus:outline-none"
                                    :class="activeSlide === {{ $dotIdx }} ? 'w-6 bg-slate-900 dark:bg-white' : 'w-2 bg-slate-400/50 dark:bg-slate-600'"
                                    aria-label="Slide {{ $dotIdx + 1 }}"
                                ></button>
                            @endforeach
                        </div>

                        <div>
                            <a 
                                href="{{ $slide['buttonUrl'] }}"
                                class="inline-flex items-center justify-between min-w-[160px] sm:min-w-[170px] bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs sm:text-sm font-semibold px-5 sm:px-6 py-2.5 rounded-lg transition-colors shadow-xs group"
                            >
                                <span>{{ $slide['buttonText'] }}</span>
                                <span class="text-xs transition-transform duration-150 group-hover:translate-x-1 font-bold">›</span>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="hidden lg:block relative z-10 w-full border-b border-slate-200/80 dark:border-slate-800 overflow-hidden transition-colors">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8 sm:gap-14 overflow-x-auto custom-scrollbar py-0.5">
                    @foreach ($heroSlides as $index => $slide)
                        <button 
                            type="button"
                            @click="setSlide({{ $index }})"
                            class="relative pb-2.5 text-left transition-colors duration-150 focus:outline-none cursor-pointer shrink-0"
                        >
                            <span 
                                class="text-xs sm:text-[13px] tracking-tight transition-colors"
                                :class="activeSlide === {{ $index }} ? 'text-slate-900 dark:text-white font-bold' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-normal'"
                            >
                                {{ $slide['tab'] }}
                            </span>

                            <div 
                                x-show="activeSlide === {{ $index }}"
                                class="absolute -bottom-[1px] left-0 right-0 h-[3px] bg-blue-600 dark:bg-blue-400"
                            ></div>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="w-full border-b border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden transition-colors">
        <div class="mx-auto max-w-7xl px-0 lg:px-8">
            <div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-y lg:divide-y-0 divide-slate-200/80 dark:divide-slate-800 border-t lg:border-t-0 border-slate-200/80 dark:border-slate-800">
                
                <a href="#about" class="group flex items-center justify-between p-3.5 sm:p-4 lg:p-5 hover:bg-slate-50/80 dark:hover:bg-slate-800/60 transition-colors">
                    <div class="pr-2 min-w-0 flex-1">
                        <div class="flex items-center gap-1.5 text-xs sm:text-sm lg:text-[15px] font-bold text-blue-600 dark:text-blue-400 truncate">
                            <span>Jalur Belajar</span>
                            <span class="text-xs shrink-0">🎯</span>
                        </div>
                        <p class="hidden lg:block text-xs text-slate-500 dark:text-slate-400 leading-normal line-clamp-2 mt-1">
                            Akses kurikulum pembelajaran modular dan latihan coding industri
                        </p>
                    </div>
                    <span class="text-slate-400 group-hover:text-slate-800 dark:group-hover:text-white text-base sm:text-lg transition-transform group-hover:translate-x-0.5 shrink-0 font-bold">›</span>
                </a>

                <a href="{{ route('login') }}" class="group flex items-center justify-between p-3.5 sm:p-4 lg:p-5 hover:bg-slate-50/80 dark:hover:bg-slate-800/60 transition-colors">
                    <div class="pr-2 min-w-0 flex-1">
                        <div class="text-xs sm:text-sm lg:text-[15px] font-bold text-slate-900 dark:text-white truncate">
                            Interactive Sandbox
                        </div>
                        <p class="hidden lg:block text-xs text-slate-500 dark:text-slate-400 leading-normal line-clamp-2 mt-1">
                            Lingkungan coding terisolasi untuk eksekusi program mandiri dan aman
                        </p>
                    </div>
                    <span class="text-slate-400 group-hover:text-slate-800 dark:group-hover:text-white text-base sm:text-lg transition-transform group-hover:translate-x-0.5 shrink-0 font-bold">›</span>
                </a>

                <a href="#about" class="group flex items-center justify-between p-3.5 sm:p-4 lg:p-5 hover:bg-slate-50/80 dark:hover:bg-slate-800/60 transition-colors">
                    <div class="pr-2 min-w-0 flex-1">
                        <div class="text-xs sm:text-sm lg:text-[15px] font-bold text-slate-900 dark:text-white truncate">
                            Standar Kompetensi
                        </div>
                        <p class="hidden lg:block text-xs text-slate-500 dark:text-slate-400 leading-normal line-clamp-2 mt-1">
                            Evaluasi berbasis penguasaan materi nyata (Mastery-based Progression)
                        </p>
                    </div>
                    <span class="text-slate-400 group-hover:text-slate-800 dark:group-hover:text-white text-base sm:text-lg transition-transform group-hover:translate-x-0.5 shrink-0 font-bold">›</span>
                </a>

                <a href="{{ route('login') }}" class="group flex items-center justify-between p-3.5 sm:p-4 lg:p-5 hover:bg-slate-50/80 dark:hover:bg-slate-800/60 transition-colors">
                    <div class="pr-2 min-w-0 flex-1">
                        <div class="text-xs sm:text-sm lg:text-[15px] font-bold text-slate-900 dark:text-white truncate">
                            Socratic NEXUS
                        </div>
                        <p class="hidden lg:block text-xs text-slate-500 dark:text-slate-400 leading-normal line-clamp-2 mt-1">
                            Bimbingan AI kontekstual dengan kendali penilaian tetap pada instruktur
                        </p>
                    </div>
                    <span class="text-slate-400 group-hover:text-slate-800 dark:group-hover:text-white text-base sm:text-lg transition-transform group-hover:translate-x-0.5 shrink-0 font-bold">›</span>
                </a>

            </div>
        </div>
    </div>
</section>
