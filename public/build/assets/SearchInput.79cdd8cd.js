import{o as r,f as a,b as e,r as n}from"./app.e6100a6a.js";function m(t,l){return r(),a("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true","data-slot":"icon"},[e("path",{"fill-rule":"evenodd",d:"M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z","clip-rule":"evenodd"})])}const s={for:"text",class:"block text-sm font-medium text-gray-700"},u={class:"mt-1"},i=["placeholder","value"],p={__name:"SearchInput",props:{placeholderStr:{type:String,default:"Please fill in"},modelValue:String},setup(t){return(l,o)=>(r(),a("div",null,[e("label",s,[n(l.$slots,"default")]),e("div",u,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md",placeholder:t.placeholderStr,onInput:o[0]||(o[0]=d=>l.$emit("update:modelValue",d.target.value)),value:t.modelValue},null,40,i)])]))}};export{p as _,m as r};
