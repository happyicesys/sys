import{o as r,f as a,b as e,r as n}from"./app.1f3b9bf1.js";function m(l,t){return r(),a("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[e("path",{"fill-rule":"evenodd",d:"M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z","clip-rule":"evenodd"})])}const s={for:"text",class:"block text-sm font-medium text-gray-700"},u={class:"mt-1"},i=["placeholder","value"],p={__name:"SearchInput",props:{placeholderStr:{type:String,default:"Please fill in"},modelValue:String},setup(l){return(t,o)=>(r(),a("div",null,[e("label",s,[n(t.$slots,"default")]),e("div",u,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md",placeholder:l.placeholderStr,onInput:o[0]||(o[0]=d=>t.$emit("update:modelValue",d.target.value)),value:l.modelValue},null,40,i)])]))}};export{p as _,m as r};