import{o as r,g as s,d as e,r as d}from"./app.7b13628b.js";const n={for:"text",class:"block text-sm font-medium text-gray-700"},u={class:"mt-1"},i=["placeholder","value"],m={__name:"SearchInput",props:{placeholderStr:{type:String,default:"Please fill in"},modelValue:String},setup(t){return(l,o)=>(r(),s("div",null,[e("label",n,[d(l.$slots,"default")]),e("div",u,[e("input",{type:"text",class:"shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md",placeholder:t.placeholderStr,onInput:o[0]||(o[0]=a=>l.$emit("update:modelValue",a.target.value)),value:t.modelValue},null,40,i)])]))}};export{m as _};
