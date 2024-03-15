import{g as x,T as i,h as w,c as b,a as s,w as r,p as C,o as n,b as o,f as d,l as T,t as V,d as f,u as g,e as k}from"./app.772f9cda.js";import{_}from"./Button.7ae31b12.js";import{_ as v}from"./FormInput.d3af3c8a.js";import{_ as S}from"./Modal.83bd6b54.js";import{r as $}from"./ArrowUturnLeftIcon.8f9cbd8a.js";import{r as B}from"./CheckCircleIcon.5965dd04.js";import"./keyboard.95c1f932.js";import"./disposables.dca62706.js";const N={class:"flex flex-col md:flex-row space-x-2"},M={key:0,class:"text-gray-600"},E={key:1},j={key:2,class:"text-gray-600"},D={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},F={class:"sm:col-span-6"},U={class:"sm:col-span-6"},q={class:"sm:col-span-6"},L={class:"flex space-x-1 mt-5 justify-end"},O=o("span",null," Back ",-1),W=o("span",null," Save ",-1),Q={__name:"Form",props:{locationType:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(m,{emit:y}){const a=m,c=y,e=x(i(p()));w(()=>{e.value=a.locationType?i(a.locationType):i(p())});function p(){return{name:"",weightage:""}}function h(){e.value.clearErrors(),a.type==="create"&&e.value.post("/location-types/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),a.type==="update"&&e.value.post("/location-types/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(u,t)=>(n(),b(C,{to:"body"},[s(S,{open:m.showModal,onModalClose:t[3]||(t[3]=l=>u.$emit("modalClose"))},{header:r(()=>[o("div",N,[a.locationType?(n(),d("span",M," Editing ")):T("",!0),a.locationType?(n(),d("span",E,V(a.locationType.name),1)):(n(),d("span",j," Create New Location Type "))])]),default:r(()=>[o("form",{onSubmit:k(h,["prevent"]),id:"submit"},[o("div",D,[o("div",F,[s(v,{modelValue:e.value.name,"onUpdate:modelValue":t[0]||(t[0]=l=>e.value.name=l),error:e.value.errors.name,required:"true"},{default:r(()=>[f(" Name ")]),_:1},8,["modelValue","error"])]),o("div",U,[s(v,{modelValue:e.value.weightage,"onUpdate:modelValue":t[1]||(t[1]=l=>e.value.weightage=l),error:e.value.errors.weightage},{default:r(()=>[f(" Weightage ")]),_:1},8,["modelValue","error"])])]),o("div",q,[o("div",L,[s(_,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:t[2]||(t[2]=l=>u.$emit("modalClose")),form:"submit"},{default:r(()=>[s(g($),{class:"w-4 h-4"}),O]),_:1}),s(_,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[s(g(B),{class:"w-4 h-4"}),W]),_:1})])])],32)]),_:1},8,["open"])]))}};export{Q as default};